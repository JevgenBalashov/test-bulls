<?php

namespace App\Http\Controllers;

use App\Http\Requests\SourceRequest;
use App\Jobs\ImportSheetSource;
use App\Models\SheetMapping;
use App\Models\SheetMappingColumn;
use App\Models\SheetSource;
use App\Models\SheetTab;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class SourceController extends Controller
{
    public function index(): Response
    {
        $sources = SheetSource::query()
            ->withCount('tabs')
            ->with('latestRun')
            ->orderBy('name')
            ->get()
            ->map(fn (SheetSource $source): array => [
                'id' => $source->id,
                'name' => $source->name,
                'spreadsheet_id' => $source->spreadsheet_id,
                'is_active' => $source->is_active,
                'last_imported_at' => $source->last_imported_at?->toDateTimeString(),
                'tabs_count' => $source->tabs_count,
                'latest_run' => $source->latestRun ? [
                    'id' => $source->latestRun->id,
                    'status' => $source->latestRun->status,
                ] : null,
            ]);

        return Inertia::render('Sources/Index', ['sources' => $sources]);
    }

    public function show(SheetSource $source): Response
    {
        $source->load('tabs.mappings.columns');

        return Inertia::render('Sources/Show', [
            'source' => [
                'id' => $source->id,
                'name' => $source->name,
                'spreadsheet_id' => $source->spreadsheet_id,
                'is_active' => $source->is_active,
                'last_imported_at' => $source->last_imported_at?->toDateTimeString(),
            ],
            'tabs' => $source->tabs->map(fn (SheetTab $tab): array => [
                'id' => $tab->id,
                'tab_name' => $tab->tab_name,
                'header_row' => $tab->header_row,
                'data_start_row' => $tab->data_start_row,
                'is_active' => $tab->is_active,
                'mappings' => $tab->mappings->map($this->presentMapping(...))->all(),
            ]),
            'dataTypes' => SheetMappingColumn::DATA_TYPES,
            'writeModes' => ['upsert', 'insert'],
        ]);
    }

    public function store(SourceRequest $request): RedirectResponse
    {
        $source = SheetSource::create($request->validated());

        return to_route('sources.show', $source)->with('status', 'Source created.');
    }

    public function update(SourceRequest $request, SheetSource $source): RedirectResponse
    {
        $source->update($request->validated());

        return back()->with('status', 'Source updated.');
    }

    public function destroy(SheetSource $source): RedirectResponse
    {
        $source->delete();

        return to_route('sources.index')->with('status', 'Source deleted.');
    }

    public function import(SheetSource $source): RedirectResponse
    {
        ImportSheetSource::dispatch($source);

        return back()->with('status', "Import queued for {$source->name}.");
    }

    private function presentMapping(SheetMapping $mapping): array
    {
        $existing = Schema::hasTable($mapping->target_table)
            ? Schema::getColumnListing($mapping->target_table)
            : null;

        return [
            'id' => $mapping->id,
            'target_table' => $mapping->target_table,
            'write_mode' => $mapping->write_mode,
            'is_active' => $mapping->is_active,
            'table_exists' => $existing !== null,
            'columns' => $mapping->columns->map(fn (SheetMappingColumn $column): array => [
                'id' => $column->id,
                'source_column' => $column->source_column,
                'target_column' => $column->target_column,
                'data_type' => $column->data_type,
                'is_nullable' => $column->is_nullable,
                'is_unique_key' => $column->is_unique_key,
                'pending_in_table' => $existing !== null && ! in_array($column->target_column, $existing, true),
            ])->all(),
        ];
    }
}
