<?php

namespace App\Services\GoogleSheets;

use RuntimeException;

class SheetReadException extends RuntimeException
{
    public static function missingCredentials(string $path): self
    {
        return new self("Google credentials file not found at [{$path}].");
    }

    public static function emptyHeaderRow(int $row): self
    {
        return new self("Header row {$row} contains no column names.");
    }

    public static function duplicateHeader(string $header): self
    {
        return new self("Column [{$header}] appears more than once in the header row.");
    }

    public static function tabNotFound(string $tab, string $spreadsheetId): self
    {
        return new self("Tab [{$tab}] was not found in spreadsheet [{$spreadsheetId}].");
    }
}
