<?php

namespace App\Services\Schema;

use App\Models\SheetMapping;
use App\Models\SheetMappingColumn;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SchemaSyncService
{
    public const RESERVED_TABLES = [
        'migrations',
        'users',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sheet_sources',
        'sheet_tabs',
        'sheet_mappings',
        'sheet_mapping_columns',
        'import_runs',
        'import_run_items',
    ];

    public function sync(SheetMapping $mapping): array
    {
        $table = $mapping->target_table;

        $this->guardIdentifier($table);

        if (in_array($table, self::RESERVED_TABLES, true)) {
            throw SchemaSyncException::reservedTable($table);
        }

        $columns = $mapping->columns;

        if ($columns->isEmpty()) {
            throw SchemaSyncException::withoutColumns($table);
        }

        foreach ($columns as $column) {
            $this->guardIdentifier($column->target_column);
        }

        $added = Schema::hasTable($table)
            ? $this->addMissingColumns($table, $columns)
            : $this->createTable($table, $columns);

        $this->ensureUniqueIndex($table, $columns);

        return $added;
    }

    private function createTable(string $table, Collection $columns): array
    {
        Schema::create($table, function (Blueprint $blueprint) use ($columns) {
            $blueprint->id();

            foreach ($columns as $column) {
                $this->defineColumn($blueprint, $column, $column->is_nullable);
            }

            $blueprint->timestamp('imported_at')->nullable();
            $blueprint->timestamps();
        });

        return $columns->pluck('target_column')->all();
    }

    private function addMissingColumns(string $table, Collection $columns): array
    {
        $existing = Schema::getColumnListing($table);

        $missing = $columns->reject(
            fn (SheetMappingColumn $column): bool => in_array($column->target_column, $existing, true)
        )->values();

        if ($missing->isEmpty()) {
            return [];
        }

        Schema::table($table, function (Blueprint $blueprint) use ($missing) {
            foreach ($missing as $column) {
                $this->defineColumn($blueprint, $column, true);
            }
        });

        return $missing->pluck('target_column')->all();
    }

    private function defineColumn(Blueprint $blueprint, SheetMappingColumn $column, bool $nullable): void
    {
        $definition = match ($column->data_type) {
            'string' => $blueprint->string($column->target_column),
            'text' => $blueprint->text($column->target_column),
            'integer' => $blueprint->bigInteger($column->target_column),
            'decimal' => $blueprint->decimal($column->target_column, 15, 4),
            'boolean' => $blueprint->boolean($column->target_column),
            'date' => $blueprint->date($column->target_column),
            'datetime' => $blueprint->dateTime($column->target_column),
            default => throw SchemaSyncException::unknownDataType($column->data_type),
        };

        $definition->nullable($nullable);
    }

    private function ensureUniqueIndex(string $table, Collection $columns): void
    {
        $keys = $columns->where('is_unique_key', true)
            ->pluck('target_column')
            ->sort()
            ->values()
            ->all();

        if ($keys === []) {
            return;
        }

        foreach (Schema::getIndexes($table) as $index) {
            $indexed = $index['columns'];
            sort($indexed);

            if ($index['unique'] && $indexed === $keys) {
                return;
            }
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($keys, $this->indexName($table, $keys)));
    }

    private function indexName(string $table, array $keys): string
    {
        return substr($table.'_'.implode('_', $keys), 0, 56).'_unique';
    }

    private function guardIdentifier(string $identifier): void
    {
        if (preg_match('/^[a-z_][a-z0-9_]*$/i', $identifier) !== 1 || strlen($identifier) > 64) {
            throw SchemaSyncException::invalidIdentifier($identifier);
        }
    }
}
