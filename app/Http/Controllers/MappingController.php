<?php

namespace App\Http\Controllers;

use App\Http\Requests\MappingRequest;
use App\Models\SheetMapping;
use App\Models\SheetTab;
use Illuminate\Http\RedirectResponse;

class MappingController extends Controller
{
    public function store(MappingRequest $request, SheetTab $tab): RedirectResponse
    {
        $tab->mappings()->create($request->validated());

        return back()->with('status', 'Target table added.');
    }

    public function update(MappingRequest $request, SheetMapping $mapping): RedirectResponse
    {
        $mapping->update($request->validated());

        return back()->with('status', 'Target table updated.');
    }

    public function destroy(SheetMapping $mapping): RedirectResponse
    {
        $mapping->delete();

        return back()->with('status', 'Target table removed from the configuration.');
    }
}
