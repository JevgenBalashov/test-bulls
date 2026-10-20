<?php

namespace App\Services\Import;

use RuntimeException;

class ValueCastException extends RuntimeException
{
    public static function unknownDataType(string $type): self
    {
        return new self("Data type [{$type}] cannot be cast.");
    }

    public static function notANumber(string $value, string $column): self
    {
        return new self("Column [{$column}] expects a number, got [{$value}].");
    }

    public static function notABoolean(string $value, string $column): self
    {
        return new self("Column [{$column}] expects a boolean, got [{$value}].");
    }

    public static function notADate(string $value, string $column): self
    {
        return new self("Column [{$column}] expects a date, got [{$value}].");
    }
}
