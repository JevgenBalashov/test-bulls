<?php

namespace App\Services\GoogleSheets;

use Closure;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Sheets;

class GoogleSheetsClient
{
    private ?Sheets $sheets = null;

    public function __construct(private readonly Closure $factory) {}

    public function readTab(string $spreadsheetId, string $tabName, int $headerRow = 1, int $dataStartRow = 2): SheetData
    {
        try {
            $response = $this->sheets()->spreadsheets_values->get($spreadsheetId, $this->range($tabName));
        } catch (GoogleServiceException $exception) {
            if ($exception->getCode() === 400) {
                throw SheetReadException::tabNotFound($tabName, $spreadsheetId);
            }

            throw $exception;
        }

        return SheetData::fromValues($response->getValues() ?? [], $headerRow, $dataStartRow);
    }

    private function sheets(): Sheets
    {
        return $this->sheets ??= ($this->factory)();
    }

    private function range(string $tabName): string
    {
        return sprintf("'%s'!%s", str_replace("'", "''", $tabName), config('services.google.read_range'));
    }
}
