<?php

namespace Tests\Feature;

use App\Services\GoogleSheets\GoogleSheetsClient;
use App\Services\GoogleSheets\SheetReadException;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Sheets;
use Google\Service\Sheets\Resource\SpreadsheetsValues;
use Google\Service\Sheets\ValueRange;
use Mockery;
use Tests\TestCase;

class GoogleSheetsClientTest extends TestCase
{
    public function test_it_quotes_the_tab_name_when_building_the_range(): void
    {
        $client = $this->clientExpecting(
            "'Sales 2026'!A1:ZZ",
            new ValueRange(['values' => [['date'], ['2026-01-05']]]),
        );

        $data = $client->readTab('spreadsheet-id', 'Sales 2026');

        $this->assertSame(['date'], $data->headers);
    }

    public function test_it_escapes_apostrophes_in_the_tab_name(): void
    {
        $client = $this->clientExpecting(
            "'Anna''s sheet'!A1:ZZ",
            new ValueRange(['values' => [['date'], ['2026-01-05']]]),
        );

        $client->readTab('spreadsheet-id', "Anna's sheet");
    }

    public function test_it_handles_a_completely_empty_tab(): void
    {
        $client = $this->clientExpecting("'Empty'!A1:ZZ", new ValueRange);

        $this->expectException(SheetReadException::class);

        $client->readTab('spreadsheet-id', 'Empty');
    }

    public function test_it_translates_a_bad_range_into_a_missing_tab_error(): void
    {
        $values = Mockery::mock(SpreadsheetsValues::class);
        $values->shouldReceive('get')->andThrow(new GoogleServiceException('Unable to parse range', 400));

        $this->expectException(SheetReadException::class);
        $this->expectExceptionMessage('Tab [Ghost] was not found');

        $this->clientFor($values)->readTab('spreadsheet-id', 'Ghost');
    }

    public function test_it_rethrows_errors_that_are_not_about_the_range(): void
    {
        $values = Mockery::mock(SpreadsheetsValues::class);
        $values->shouldReceive('get')->andThrow(new GoogleServiceException('Forbidden', 403));

        $this->expectException(GoogleServiceException::class);

        $this->clientFor($values)->readTab('spreadsheet-id', 'Daily Sales');
    }

    private function clientExpecting(string $range, ValueRange $response): GoogleSheetsClient
    {
        $values = Mockery::mock(SpreadsheetsValues::class);
        $values->shouldReceive('get')
            ->once()
            ->with('spreadsheet-id', $range)
            ->andReturn($response);

        return $this->clientFor($values);
    }

    private function clientFor(SpreadsheetsValues $values): GoogleSheetsClient
    {
        $sheets = Mockery::mock(Sheets::class);
        $sheets->spreadsheets_values = $values;

        return new GoogleSheetsClient(fn () => $sheets);
    }
}
