<?php

namespace App\Services\Schema;

use RuntimeException;

class SchemaSyncException extends RuntimeException
{
    public static function invalidIdentifier(string $identifier): self
    {
        return new self("[{$identifier}] is not a valid table or column name.");
    }

    public static function reservedTable(string $table): self
    {
        return new self("Table [{$table}] belongs to the application and cannot be used as an import target.");
    }

    public static function unknownDataType(string $type): self
    {
        return new self("Data type [{$type}] is not supported.");
    }

    public static function withoutColumns(string $table): self
    {
        return new self("Mapping for table [{$table}] has no columns configured.");
    }
}
