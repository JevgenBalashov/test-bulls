<?php

namespace Tests\Unit;

use App\Services\GoogleSheets\SheetData;
use App\Services\GoogleSheets\SheetReadException;
use PHPUnit\Framework\TestCase;

class SheetDataTest extends TestCase
{
    public function test_it_maps_rows_by_header_name(): void
    {
        $data = SheetData::fromValues([
            ['date', 'product_name', 'price', 'amount'],
            ['2026-01-05', 'Keyboard', '49.90', '3'],
        ]);

        $this->assertSame(['date', 'product_name', 'price', 'amount'], $data->headers);
        $this->assertSame([
            'date' => '2026-01-05',
            'product_name' => 'Keyboard',
            'price' => '49.90',
            'amount' => '3',
        ], $data->rows[0]);
    }

    public function test_column_order_and_extra_columns_do_not_shift_values(): void
    {
        $data = SheetData::fromValues([
            ['Amount', 'Date', 'Currency', 'Price', 'Product', 'Manager'],
            ['3', '2026-01-05', 'EUR', '49.90', 'Keyboard', 'Anna'],
        ]);

        $this->assertSame('Keyboard', $data->rows[0]['Product']);
        $this->assertSame('2026-01-05', $data->rows[0]['Date']);
        $this->assertSame('EUR', $data->rows[0]['Currency']);
    }

    public function test_it_pads_rows_that_end_early(): void
    {
        $data = SheetData::fromValues([
            ['date', 'name', 'note'],
            ['2026-01-05', 'Keyboard'],
        ]);

        $this->assertSame('', $data->rows[0]['note']);
    }

    public function test_it_skips_blank_rows(): void
    {
        $data = SheetData::fromValues([
            ['date', 'name'],
            ['2026-01-05', 'Keyboard'],
            ['', ''],
            [],
            ['2026-01-06', 'Mouse'],
        ]);

        $this->assertCount(2, $data->rows);
        $this->assertSame('Mouse', $data->rows[1]['name']);
    }

    public function test_it_ignores_unnamed_columns_without_losing_alignment(): void
    {
        $data = SheetData::fromValues([
            ['date', '   ', 'name'],
            ['2026-01-05', 'scratch value', 'Keyboard'],
        ]);

        $this->assertSame(['date', 'name'], $data->headers);
        $this->assertSame('Keyboard', $data->rows[0]['name']);
    }

    public function test_it_trims_surrounding_whitespace(): void
    {
        $data = SheetData::fromValues([
            ['  date  ', ' name '],
            [' 2026-01-05 ', "Keyboard\n"],
        ]);

        $this->assertSame(['date', 'name'], $data->headers);
        $this->assertSame('2026-01-05', $data->rows[0]['date']);
        $this->assertSame('Keyboard', $data->rows[0]['name']);
    }

    public function test_it_honours_custom_header_and_data_rows(): void
    {
        $data = SheetData::fromValues([
            ['Quarterly report', '', ''],
            ['date', 'name', 'amount'],
            ['2026-01-05', 'Keyboard', '3'],
        ], headerRow: 2, dataStartRow: 3);

        $this->assertSame(['date', 'name', 'amount'], $data->headers);
        $this->assertCount(1, $data->rows);
    }

    public function test_it_reports_headers_the_configuration_expects_but_the_sheet_lacks(): void
    {
        $data = SheetData::fromValues([
            ['date', 'name'],
            ['2026-01-05', 'Keyboard'],
        ]);

        $this->assertTrue($data->has('date'));
        $this->assertSame(['price'], $data->missingHeaders(['date', 'price']));
    }

    public function test_it_rejects_duplicate_headers(): void
    {
        $this->expectException(SheetReadException::class);

        SheetData::fromValues([
            ['date', 'name', 'name'],
            ['2026-01-05', 'Keyboard', 'Mouse'],
        ]);
    }

    public function test_it_rejects_an_empty_header_row(): void
    {
        $this->expectException(SheetReadException::class);

        SheetData::fromValues([
            ['', '  '],
            ['2026-01-05', 'Keyboard'],
        ]);
    }
}
