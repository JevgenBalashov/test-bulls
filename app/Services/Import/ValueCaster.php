<?php

namespace App\Services\Import;

use Carbon\CarbonImmutable;
use Throwable;

class ValueCaster
{
    public function cast(?string $value, string $type, string $column): string|int|float|bool|null
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return match ($type) {
            'string', 'text' => $value,
            'integer' => (int) round($this->number($value, $column)),
            'decimal' => $this->number($value, $column),
            'boolean' => $this->boolean($value, $column),
            'date' => $this->date($value, $column)->toDateString(),
            'datetime' => $this->date($value, $column)->toDateTimeString(),
            default => throw ValueCastException::unknownDataType($type),
        };
    }

    private function number(string $value, string $column): float
    {
        $cleaned = preg_replace('/[^\d,.\-]/u', '', $value);

        $lastComma = strrpos($cleaned, ',');
        $lastDot = strrpos($cleaned, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $cleaned = $lastComma > $lastDot
                ? str_replace(['.', ','], ['', '.'], $cleaned)
                : str_replace(',', '', $cleaned);
        } elseif ($lastComma !== false) {
            $cleaned = preg_match('/^-?\d{1,3}(,\d{3})+$/', $cleaned) === 1
                ? str_replace(',', '', $cleaned)
                : str_replace(',', '.', $cleaned);
        }

        if (! is_numeric($cleaned)) {
            throw ValueCastException::notANumber($value, $column);
        }

        return (float) $cleaned;
    }

    private function boolean(string $value, string $column): bool
    {
        return match (mb_strtolower($value)) {
            '1', 'true', 'yes', 'y' => true,
            '0', 'false', 'no', 'n' => false,
            default => throw ValueCastException::notABoolean($value, $column),
        };
    }

    private function date(string $value, string $column): CarbonImmutable
    {
        if (strtotime($value) === false) {
            throw ValueCastException::notADate($value, $column);
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            throw ValueCastException::notADate($value, $column);
        }
    }
}
