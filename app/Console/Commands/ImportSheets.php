<?php

namespace App\Console\Commands;

use App\Jobs\ImportSheetSource;
use App\Models\SheetSource;
use App\Services\GoogleSheets\SheetReadException;
use App\Services\Import\ImportService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class ImportSheets extends Command
{
    protected $signature = 'sheets:import
        {--source=* : Limit the run to the given source ids or spreadsheet ids}
        {--sync : Import right away instead of queueing the work}';

    protected $description = 'Import every active Google Sheets source into its target tables';

    public function handle(): int
    {
        $sources = $this->sources();

        if ($sources->isEmpty()) {
            $this->components->warn('No active sources matched.');

            return self::SUCCESS;
        }

        if (! $this->option('sync')) {
            $sources->each(function (SheetSource $source) {
                ImportSheetSource::dispatch($source);

                $this->components->twoColumnDetail($source->name, 'queued');
            });

            return self::SUCCESS;
        }

        try {
            $import = $this->laravel->make(ImportService::class);
        } catch (SheetReadException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($sources as $source) {
            $run = $import->import($source);

            $this->components->twoColumnDetail(
                $source->name,
                sprintf('%s, %d rows written', $run->status, $run->rows_written),
            );
        }

        return self::SUCCESS;
    }

    private function sources(): Collection
    {
        $filters = $this->option('source');

        return SheetSource::query()
            ->where('is_active', true)
            ->when($filters, fn ($query) => $query->where(
                fn ($nested) => $nested->whereIn('id', array_filter($filters, is_numeric(...)))
                    ->orWhereIn('spreadsheet_id', $filters)
            ))
            ->get();
    }
}
