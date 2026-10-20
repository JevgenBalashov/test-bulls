<?php

namespace App\Http\Controllers;

use App\Models\ImportRun;
use App\Models\ImportRunItem;
use Inertia\Inertia;
use Inertia\Response;

class RunController extends Controller
{
    public function index(): Response
    {
        $runs = ImportRun::query()
            ->with('source')
            ->withCount('items')
            ->latest('id')
            ->paginate(20)
            ->through(fn (ImportRun $run): array => [
                'id' => $run->id,
                'source' => ['id' => $run->source->id, 'name' => $run->source->name],
                'status' => $run->status,
                'started_at' => $run->started_at?->toDateTimeString(),
                'finished_at' => $run->finished_at?->toDateTimeString(),
                'rows_read' => $run->rows_read,
                'rows_written' => $run->rows_written,
                'items_count' => $run->items_count,
            ]);

        return Inertia::render('Runs/Index', ['runs' => $runs]);
    }

    public function show(ImportRun $run): Response
    {
        $run->load(['source', 'items']);

        return Inertia::render('Runs/Show', [
            'run' => [
                'id' => $run->id,
                'source' => ['id' => $run->source->id, 'name' => $run->source->name],
                'status' => $run->status,
                'started_at' => $run->started_at?->toDateTimeString(),
                'finished_at' => $run->finished_at?->toDateTimeString(),
                'rows_read' => $run->rows_read,
                'rows_written' => $run->rows_written,
                'error_message' => $run->error_message,
                'items' => $run->items->map(fn (ImportRunItem $item): array => [
                    'id' => $item->id,
                    'tab_name' => $item->tab_name,
                    'target_table' => $item->target_table,
                    'status' => $item->status,
                    'rows_read' => $item->rows_read,
                    'rows_written' => $item->rows_written,
                    'rows_skipped' => $item->rows_skipped,
                    'added_columns' => $item->added_columns ?? [],
                    'error_message' => $item->error_message,
                ])->all(),
            ],
        ]);
    }
}
