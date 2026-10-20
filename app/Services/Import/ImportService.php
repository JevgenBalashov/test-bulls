<?php

namespace App\Services\Import;

use App\Models\ImportRun;
use App\Models\SheetMapping;
use App\Models\SheetSource;
use App\Models\SheetTab;
use App\Services\GoogleSheets\GoogleSheetsClient;
use App\Services\GoogleSheets\SheetData;
use App\Services\Schema\SchemaSyncService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportService
{
    private const CHUNK_SIZE = 500;

    public function __construct(
        private readonly GoogleSheetsClient $sheets,
        private readonly SchemaSyncService $schema,
        private readonly ValueCaster $caster,
    ) {}

    public function import(SheetSource $source): ImportRun
    {
        $run = $source->runs()->create([
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            foreach ($source->tabs()->where('is_active', true)->get() as $tab) {
                $this->importTab($run, $tab);
            }
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            return $run;
        }

        return $this->finish($run, $source);
    }

    private function importTab(ImportRun $run, SheetTab $tab): void
    {
        $mappings = $tab->mappings()->where('is_active', true)->with('columns')->get();

        if ($mappings->isEmpty()) {
            return;
        }

        try {
            $data = $this->sheets->readTab(
                $tab->source->spreadsheet_id,
                $tab->tab_name,
                $tab->header_row,
                $tab->data_start_row,
            );
        } catch (Throwable $exception) {
            $mappings->each(fn (SheetMapping $mapping) => $this->recordFailure($run, $tab, $mapping, $exception->getMessage()));

            return;
        }

        foreach ($mappings as $mapping) {
            $this->importMapping($run, $tab, $mapping, $data);
        }
    }

    private function importMapping(ImportRun $run, SheetTab $tab, SheetMapping $mapping, SheetData $data): void
    {
        $missing = $data->missingHeaders($mapping->columns->pluck('source_column')->all());

        if ($missing !== []) {
            $this->recordFailure($run, $tab, $mapping, 'Sheet is missing columns: '.implode(', ', $missing));

            return;
        }

        try {
            $addedColumns = $this->schema->sync($mapping);
        } catch (Throwable $exception) {
            $this->recordFailure($run, $tab, $mapping, $exception->getMessage());

            return;
        }

        $rows = [];
        $skipped = 0;
        $error = null;

        foreach ($data->rows as $row) {
            try {
                $rows[] = $this->buildRow($mapping, $row);
            } catch (ValueCastException $exception) {
                $skipped++;
                $error ??= $exception->getMessage();
            }
        }

        $written = $this->write($mapping, $rows);

        $run->items()->create([
            'sheet_mapping_id' => $mapping->id,
            'tab_name' => $tab->tab_name,
            'target_table' => $mapping->target_table,
            'status' => 'success',
            'rows_read' => count($data->rows),
            'rows_written' => $written,
            'rows_skipped' => $skipped,
            'added_columns' => $addedColumns,
            'error_message' => $error,
        ]);
    }

    private function buildRow(SheetMapping $mapping, array $row): array
    {
        $timestamp = now();
        $built = [];

        foreach ($mapping->columns as $column) {
            $built[$column->target_column] = $this->caster->cast(
                $row[$column->source_column] ?? null,
                $column->data_type,
                $column->target_column,
            );
        }

        return $built + [
            'imported_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    private function write(SheetMapping $mapping, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $keys = $mapping->columns->where('is_unique_key', true)->pluck('target_column')->all();

        if ($mapping->write_mode === 'insert' || $keys === []) {
            foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
                DB::table($mapping->target_table)->insert($chunk);
            }

            return count($rows);
        }

        $rows = $this->deduplicate($rows, $keys);
        $updatable = array_values(array_diff(array_keys($rows[0]), [...$keys, 'created_at']));

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            DB::table($mapping->target_table)->upsert($chunk, $keys, $updatable);
        }

        return count($rows);
    }

    private function deduplicate(array $rows, array $keys): array
    {
        $unique = [];

        foreach ($rows as $row) {
            $signature = implode("\0", array_map(fn (string $key): string => (string) $row[$key], $keys));
            $unique[$signature] = $row;
        }

        return array_values($unique);
    }

    private function recordFailure(ImportRun $run, SheetTab $tab, SheetMapping $mapping, string $message): void
    {
        $run->items()->create([
            'sheet_mapping_id' => $mapping->id,
            'tab_name' => $tab->tab_name,
            'target_table' => $mapping->target_table,
            'status' => 'failed',
            'error_message' => $message,
        ]);
    }

    private function finish(ImportRun $run, SheetSource $source): ImportRun
    {
        $items = $run->items()->get();

        $run->update([
            'status' => $this->statusFor($items),
            'finished_at' => now(),
            'rows_read' => $items->sum('rows_read'),
            'rows_written' => $items->sum('rows_written'),
        ]);

        $source->update(['last_imported_at' => now()]);

        return $run->refresh();
    }

    private function statusFor(Collection $items): string
    {
        if ($items->isEmpty() || ($items->every('status', 'success') && $items->sum('rows_skipped') === 0)) {
            return 'success';
        }

        return $items->every('status', 'failed') ? 'failed' : 'partial';
    }
}
