<?php

namespace App\Console\Commands;

use App\Services\GoogleSheets\GoogleSheetsClient;
use App\Services\GoogleSheets\SheetReadException;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Console\Command;

class ReadSheetTab extends Command
{
    protected $signature = 'sheets:read
        {spreadsheet : Spreadsheet id taken from the document url}
        {tab : Name of the tab to read}
        {--header=1 : Row number holding the column names}
        {--start=2 : First row holding data}
        {--limit=10 : How many rows to print}';

    protected $description = 'Preview a Google Sheets tab the way the importer sees it';

    public function handle(): int
    {
        try {
            $data = $this->laravel->make(GoogleSheetsClient::class)->readTab(
                $this->argument('spreadsheet'),
                $this->argument('tab'),
                (int) $this->option('header'),
                (int) $this->option('start'),
            );
        } catch (SheetReadException|GoogleServiceException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $shown = array_slice($data->rows, 0, max(1, (int) $this->option('limit')));

        $this->table($data->headers, array_map(array_values(...), $shown));
        $this->components->info(sprintf(
            'Showing %d of %d rows, %d columns.',
            count($shown),
            count($data->rows),
            count($data->headers),
        ));

        return self::SUCCESS;
    }
}
