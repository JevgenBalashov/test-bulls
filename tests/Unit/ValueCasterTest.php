<?php

namespace Tests\Unit;

use App\Services\Import\ValueCaster;
use App\Services\Import\ValueCastException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValueCasterTest extends TestCase
{
    private ValueCaster $caster;

    protected function setUp(): void
    {
        parent::setUp();

        $this->caster = new ValueCaster;
    }

    public static function numbers(): array
    {
        return [
            'plain' => ['49.90', 49.90],
            'european decimal' => ['49,90', 49.90],
            'thousands with comma' => ['1,234', 1234.0],
            'thousands and decimal, english' => ['1,234.56', 1234.56],
            'thousands and decimal, european' => ['1.234,56', 1234.56],
            'spaced thousands' => ['1 234,56', 1234.56],
            'trailing currency' => ['49.90 €', 49.90],
            'leading currency' => ['$1,999.00', 1999.0],
            'negative' => ['-15,5', -15.5],
        ];
    }

    #[DataProvider('numbers')]
    public function test_it_normalises_number_formats(string $value, float $expected): void
    {
        $this->assertSame($expected, $this->caster->cast($value, 'decimal', 'price'));
    }

    public function test_it_rounds_decimals_when_the_column_is_an_integer(): void
    {
        $this->assertSame(3, $this->caster->cast('2.6', 'integer', 'amount'));
    }

    public function test_it_treats_blank_cells_as_null(): void
    {
        $this->assertNull($this->caster->cast('', 'decimal', 'price'));
        $this->assertNull($this->caster->cast('   ', 'string', 'name'));
        $this->assertNull($this->caster->cast(null, 'date', 'date'));
    }

    public function test_it_normalises_dates_and_datetimes(): void
    {
        $this->assertSame('2026-01-05', $this->caster->cast('2026-01-05', 'date', 'date'));
        $this->assertSame('2026-01-05', $this->caster->cast('5 January 2026', 'date', 'date'));
        $this->assertSame('2026-01-05 14:30:00', $this->caster->cast('2026-01-05 14:30', 'datetime', 'seen_at'));
    }

    public function test_it_understands_common_boolean_spellings(): void
    {
        $this->assertTrue($this->caster->cast('Yes', 'boolean', 'paid'));
        $this->assertTrue($this->caster->cast('TRUE', 'boolean', 'paid'));
        $this->assertFalse($this->caster->cast('n', 'boolean', 'paid'));
        $this->assertFalse($this->caster->cast('0', 'boolean', 'paid'));
    }

    public function test_it_keeps_strings_untouched_apart_from_trimming(): void
    {
        $this->assertSame('Keyboard', $this->caster->cast('  Keyboard  ', 'string', 'name'));
    }

    public function test_it_names_the_column_when_a_value_cannot_be_cast(): void
    {
        $this->expectException(ValueCastException::class);
        $this->expectExceptionMessage('Column [price] expects a number, got [n/a].');

        $this->caster->cast('n/a', 'decimal', 'price');
    }

    public function test_it_rejects_values_that_are_not_boolean(): void
    {
        $this->expectException(ValueCastException::class);

        $this->caster->cast('maybe', 'boolean', 'paid');
    }

    public function test_it_rejects_values_that_are_not_dates(): void
    {
        $this->expectException(ValueCastException::class);

        $this->caster->cast('not a date', 'date', 'date');
    }
}
