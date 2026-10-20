<?php

namespace Tests\Feature;

use App\Models\SheetMapping;
use App\Models\SheetMappingColumn;
use App\Models\SheetSource;
use App\Models\SheetTab;
use App\Services\GoogleSheets\GoogleSheetsClient;
use App\Services\GoogleSheets\SheetData;
use App\Services\GoogleSheets\SheetReadException;
use App\Services\Import\ImportService;
use App\Services\Import\ValueCaster;
use App\Services\Schema\SchemaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Schema::dropIfExists('sales');
        Schema::dropIfExists('products');

        parent::tearDown();
    }

    public function test_it_writes_sheet_rows_into_the_target_table(): void
    {
        $source = $this->source();
        $tab = $this->tab($source);
        $this->mapping($tab, 'sales', [
            ['date', 'date', 'date', true],
            ['product_name', 'name', 'string', true],
            ['price', 'product_price', 'decimal'],
            ['amount', 'amount', 'integer'],
        ]);

        $run = $this->service($this->reader([
            ['date', 'product_name', 'price', 'amount'],
            ['2026-01-05', 'Keyboard', '49,90', '3'],
            ['2026-01-06', 'Mouse', '19.50', '7'],
        ]))->import($source);

        $this->assertSame('success', $run->status);
        $this->assertSame(2, $run->rows_written);

        $row = DB::table('sales')->where('name', 'Keyboard')->first();

        $this->assertSame('2026-01-05', $row->date);
        $this->assertEquals(49.90, $row->product_price);
        $this->assertSame(3, (int) $row->amount);
        $this->assertNotNull($row->imported_at);
    }

    public function test_it_reads_a_tab_once_even_when_it_feeds_several_tables(): void
    {
        $source = $this->source();
        $tab = $this->tab($source);

        $this->mapping($tab, 'sales', [
            ['date', 'date', 'date', true],
            ['product_name', 'name', 'string', true],
        ]);

        $this->mapping($tab, 'products', [
            ['product_name', 'name', 'string', true],
            ['price', 'last_price', 'decimal'],
        ]);

        $reader = $this->reader([
            ['date', 'product_name', 'price'],
            ['2026-01-05', 'Keyboard', '49.90'],
        ], times: 1);

        $run = $this->service($reader)->import($source);

        $this->assertSame('success', $run->status);
        $this->assertSame(1, DB::table('sales')->count());
        $this->assertSame(1, DB::table('products')->count());
    }

    public function test_running_the_same_import_twice_does_not_duplicate_rows(): void
    {
        $source = $this->source();
        $tab = $this->tab($source);
        $this->mapping($tab, 'sales', [
            ['date', 'date', 'date', true],
            ['product_name', 'name', 'string', true],
            ['price', 'product_price', 'decimal'],
        ]);

        $service = $this->service($this->reader([
            ['date', 'product_name', 'price'],
            ['2026-01-05', 'Keyboard', '49.90'],
        ], times: 2));

        $service->import($source);
        $service->import($source->fresh());

        $this->assertSame(1, DB::table('sales')->count());
    }

    public function test_repeated_key_combinations_inside_one_sheet_collapse_to_one_row(): void
    {
        $source = $this->source();
        $tab = $this->tab($source);
        $this->mapping($tab, 'sales', [
            ['date', 'date', 'date', true],
            ['product_name', 'name', 'string', true],
            ['price', 'product_price', 'decimal'],
        ]);

        $run = $this->service($this->reader([
            ['date', 'product_name', 'price'],
            ['2026-01-05', 'Keyboard', '49.90'],
            ['2026-01-05', 'Keyboard', '59.90'],
        ]))->import($source);

        $this->assertSame(1, DB::table('sales')->count());
        $this->assertSame(1, $run->rows_written);
        $this->assertEquals(59.90, DB::table('sales')->first()->product_price);
    }

    public function test_a_column_added_to_the_configuration_is_created_and_filled(): void
    {
        $source = $this->source();
        $tab = $this->tab($source);
        $mapping = $this->mapping($tab, 'sales', [
            ['date', 'date', 'date', true],
            ['product_name', 'name', 'string', true],
        ]);

        $this->service($this->reader([
            ['date', 'product_name', 'currency'],
            ['2026-01-05', 'Keyboard', 'EUR'],
        ]))->import($source);

        $this->assertFalse(Schema::hasColumn('sales', 'currency'));

        $this->column($mapping, ['currency', 'currency', 'string']);

        $run = $this->service($this->reader([
            ['date', 'product_name', 'currency'],
            ['2026-01-05', 'Keyboard', 'EUR'],
        ]))->import($source->fresh());

        $this->assertSame('EUR', DB::table('sales')->first()->currency);
        $this->assertSame(['currency'], $run->items()->first()->added_columns);
    }

    public function test_a_mapping_missing_its_source_column_fails_without_stopping_the_others(): void
    {
        $source = $this->source();
        $tab = $this->tab($source);

        $this->mapping($tab, 'sales', [
            ['product_name', 'name', 'string', true],
        ]);

        $this->mapping($tab, 'products', [
            ['product_name', 'name', 'string', true],
            ['supplier', 'supplier', 'string'],
        ]);

        $run = $this->service($this->reader([
            ['product_name'],
            ['Keyboard'],
        ]))->import($source);

        $this->assertSame('partial', $run->status);
        $this->assertSame(1, DB::table('sales')->count());
        $this->assertFalse(Schema::hasTable('products'));

        $failed = $run->items()->where('status', 'failed')->first();

        $this->assertStringContainsString('missing columns: supplier', $failed->error_message);
    }

    public function test_rows_with_unusable_values_are_skipped_and_counted(): void
    {
        $source = $this->source();
        $tab = $this->tab($source);
        $this->mapping($tab, 'sales', [
            ['date', 'date', 'date', true],
            ['product_name', 'name', 'string', true],
            ['price', 'product_price', 'decimal'],
        ]);

        $run = $this->service($this->reader([
            ['date', 'product_name', 'price'],
            ['2026-01-05', 'Keyboard', '49.90'],
            ['2026-01-06', 'Mouse', 'n/a'],
        ]))->import($source);

        $item = $run->items()->first();

        $this->assertSame('partial', $run->status);
        $this->assertSame(2, $item->rows_read);
        $this->assertSame(1, $item->rows_written);
        $this->assertSame(1, $item->rows_skipped);
        $this->assertStringContainsString('expects a number', $item->error_message);
    }

    public function test_a_tab_that_cannot_be_read_marks_every_mapping_as_failed(): void
    {
        $source = $this->source();
        $tab = $this->tab($source);
        $this->mapping($tab, 'sales', [['product_name', 'name', 'string', true]]);

        $client = Mockery::mock(GoogleSheetsClient::class);
        $client->shouldReceive('readTab')->andThrow(SheetReadException::tabNotFound('Sheet1', 'demo'));

        $run = $this->service($client)->import($source);

        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('was not found', $run->items()->first()->error_message);
    }

    public function test_it_stamps_the_source_with_the_last_import_time(): void
    {
        $source = $this->source();
        $tab = $this->tab($source);
        $this->mapping($tab, 'sales', [['product_name', 'name', 'string', true]]);

        $this->assertNull($source->last_imported_at);

        $this->service($this->reader([['product_name'], ['Keyboard']]))->import($source);

        $this->assertNotNull($source->fresh()->last_imported_at);
    }

    private function service(GoogleSheetsClient|MockInterface $client): ImportService
    {
        return new ImportService($client, new SchemaSyncService, new ValueCaster);
    }

    private function reader(array $values, int $times = 1): MockInterface
    {
        $client = Mockery::mock(GoogleSheetsClient::class);
        $client->shouldReceive('readTab')
            ->times($times)
            ->andReturn(SheetData::fromValues($values));

        return $client;
    }

    private function source(): SheetSource
    {
        return SheetSource::create([
            'name' => 'Demo',
            'spreadsheet_id' => 'demo-spreadsheet',
        ]);
    }

    private function tab(SheetSource $source): SheetTab
    {
        return SheetTab::create([
            'sheet_source_id' => $source->id,
            'tab_name' => 'Sheet1',
        ]);
    }

    private function mapping(SheetTab $tab, string $table, array $columns): SheetMapping
    {
        $mapping = SheetMapping::create([
            'sheet_tab_id' => $tab->id,
            'target_table' => $table,
        ]);

        foreach ($columns as $column) {
            $this->column($mapping, $column);
        }

        return $mapping;
    }

    private function column(SheetMapping $mapping, array $column): void
    {
        [$source, $target, $type] = $column;
        $isKey = $column[3] ?? false;

        SheetMappingColumn::create([
            'sheet_mapping_id' => $mapping->id,
            'source_column' => $source,
            'target_column' => $target,
            'data_type' => $type,
            'is_nullable' => ! $isKey,
            'is_unique_key' => $isKey,
        ]);
    }
}
