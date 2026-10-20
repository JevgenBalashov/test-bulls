<?php

namespace App\Http\Controllers;

use App\Http\Requests\MappingColumnRequest;
use App\Models\SheetMapping;
use App\Models\SheetMappingColumn;
use Illuminate\Http\RedirectResponse;

class MappingColumnController extends Controller
{
    public function store(MappingColumnRequest $request, SheetMapping $mapping): RedirectResponse
    {
        $mapping->columns()->create($request->validated());

        return back()->with('status', 'Column added to the mapping.');
    }

    public function update(MappingColumnRequest $request, SheetMappingColumn $column): RedirectResponse
    {
        $column->update($request->validated());

        return back()->with('status', 'Column updated.');
    }

    public function destroy(SheetMappingColumn $column): RedirectResponse
    {
        $column->delete();

        return back()->with('status', 'Column removed from the mapping.');
    }
}
