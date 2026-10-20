<?php

namespace App\Jobs;

use App\Models\SheetSource;
use App\Services\Import\ImportService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ImportSheetSource implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 3;

    public function __construct(public readonly SheetSource $source) {}

    public function handle(ImportService $import): void
    {
        $import->import($this->source);
    }

    public function uniqueId(): string
    {
        return (string) $this->source->id;
    }

    public function failed(Throwable $exception): void
    {
        $this->source->runs()
            ->where('status', 'running')
            ->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);
    }
}
