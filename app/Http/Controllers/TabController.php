<?php

namespace App\Http\Controllers;

use App\Http\Requests\TabRequest;
use App\Models\SheetSource;
use App\Models\SheetTab;
use App\Services\GoogleSheets\GoogleSheetsClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Throwable;

class TabController extends Controller
{
    public function store(TabRequest $request, SheetSource $source): RedirectResponse
    {
        $source->tabs()->create($request->validated());

        return back()->with('status', 'Tab added.');
    }

    public function update(TabRequest $request, SheetTab $tab): RedirectResponse
    {
        $tab->update($request->validated());

        return back()->with('status', 'Tab updated.');
    }

    public function destroy(SheetTab $tab): RedirectResponse
    {
        $tab->delete();

        return back()->with('status', 'Tab deleted.');
    }

    public function headers(SheetTab $tab): JsonResponse
    {
        try {
            $data = app(GoogleSheetsClient::class)->readTab(
                $tab->source->spreadsheet_id,
                $tab->tab_name,
                $tab->header_row,
                $tab->data_start_row,
            );
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'headers' => $data->headers,
            'rows' => count($data->rows),
        ]);
    }
}
