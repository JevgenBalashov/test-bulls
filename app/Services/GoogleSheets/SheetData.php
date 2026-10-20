<?php

namespace App\Services\GoogleSheets;

class SheetData
{
    public function __construct(
        public readonly array $headers,
        public readonly array $rows,
    ) {}

    public static function fromValues(array $values, int $headerRow = 1, int $dataStartRow = 2): self
    {
        $positions = self::headerPositions($values[$headerRow - 1] ?? []);

        if ($positions === []) {
            throw SheetReadException::emptyHeaderRow($headerRow);
        }

        $rows = [];

        foreach (array_slice($values, $dataStartRow - 1) as $row) {
            $mapped = [];

            foreach ($positions as $header => $index) {
                $mapped[$header] = trim((string) ($row[$index] ?? ''));
            }

            if (array_filter($mapped, static fn (string $value): bool => $value !== '') === []) {
                continue;
            }

            $rows[] = $mapped;
        }

        return new self(array_keys($positions), $rows);
    }

    public function has(string $header): bool
    {
        return in_array($header, $this->headers, true);
    }

    public function missingHeaders(array $expected): array
    {
        return array_values(array_diff($expected, $this->headers));
    }

    private static function headerPositions(array $headerRow): array
    {
        $positions = [];

        foreach ($headerRow as $index => $header) {
            $header = trim((string) $header);

            if ($header === '') {
                continue;
            }

            if (array_key_exists($header, $positions)) {
                throw SheetReadException::duplicateHeader($header);
            }

            $positions[$header] = $index;
        }

        return $positions;
    }
}
