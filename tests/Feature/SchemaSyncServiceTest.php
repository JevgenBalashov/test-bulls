<?php

namespace Tests\Feature;

use App\Models\SheetMapping;
use App\Models\SheetMappingColumn;
use App\Models\SheetSource;
use App\Models\SheetTab;
use App\Services\Schema\SchemaSyncException;
use App\Services\Schema\SchemaSyncService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private SchemaSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SchemaSyncService;
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sales');

        parent::tearDown();
    }

    public function test_it_creates_the_target_table_from_the_mapping(): void
    {
        $mapping = $this->mapping('sales', [
            ['target' => 'date', 'type' => 'date', 'key' => true],
            ['target' => 'name', 'type' => 'string', 'key' => true],
            ['target' => 'product_price', 'type' => 'decimal'],
            ['target' => 'amount', 'type' => 'integer'],
        ]);

        $added = $this->service->sync($mapping);

        $this->assertTrue(Schema::hasTable('sales'));
        $this->assertTrue(Schema::hasColumns('sales', ['id', 'date', 'name', 'product_price', 'amount', 'imported_at', 'created_at']));
        $this->assertSame(['date', 'name', 'product_price', 'amount'], $added);
    }

    public function test_it_adds_a_column_that_the_configuration_gained_later(): void
    {
        $mapping = $this->mapping('sales', [
            ['target' => 'date', 'type' => 'date', 'key' => true],
            ['target' => 'name', 'type' => 'string', 'key' => true],
        ]);

        $this->service->sync($mapping);
        $this->assertFalse(Schema::hasColumn('sales', 'currency'));

        $this->addColumn($mapping, ['target' => 'currency', 'type' => 'string']);

        $added = $this->service->sync($mapping->fresh());

        $this->assertSame(['currency'], $added);
        $this->assertTrue(Schema::hasColumn('sales', 'currency'));
    }

    public function test_adding_a_column_keeps_the_rows_already_stored(): void
    {
        $mapping = $this->mapping('sales', [
            ['target' => 'date', 'type' => 'date', 'key' => true],
            ['target' => 'name', 'type' => 'string', 'key' => true],
        ]);

        $this->service->sync($mapping);

        DB::table('sales')->insert(['date' => '2026-01-05', 'name' => 'Keyboard']);

        $this->addColumn($mapping, ['target' => 'manager_name', 'type' => 'string']);
        $this->service->sync($mapping->fresh());

        $row = DB::table('sales')->first();

        $this->assertSame('Keyboard', $row->name);
        $this->assertNull($row->manager_name);
    }

    public function test_columns_added_to_a_populated_table_stay_nullable(): void
    {
        $mapping = $this->mapping('sales', [
            ['target' => 'name', 'type' => 'string', 'key' => true],
        ]);

        $this->service->sync($mapping);

        $this->addColumn($mapping, ['target' => 'currency', 'type' => 'string', 'nullable' => false]);
        $this->service->sync($mapping->fresh());

        DB::table('sales')->insert(['name' => 'Keyboard']);

        $this->assertNull(DB::table('sales')->first()->currency);
    }

    public function test_it_reports_nothing_when_the_table_already_matches(): void
    {
        $mapping = $this->mapping('sales', [
            ['target' => 'name', 'type' => 'string', 'key' => true],
        ]);

        $this->service->sync($mapping);

        $this->assertSame([], $this->service->sync($mapping->fresh()));
    }

    public function test_it_creates_a_unique_index_over_the_key_columns(): void
    {
        $mapping = $this->mapping('sales', [
            ['target' => 'date', 'type' => 'date', 'key' => true],
            ['target' => 'name', 'type' => 'string', 'key' => true],
            ['target' => 'amount', 'type' => 'integer'],
        ]);

        $this->service->sync($mapping);

        DB::table('sales')->insert(['date' => '2026-01-05', 'name' => 'Keyboard', 'amount' => 1]);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('sales')->insert(['date' => '2026-01-05', 'name' => 'Keyboard', 'amount' => 2]);
    }

    public function test_it_does_not_add_the_same_unique_index_twice(): void
    {
        $mapping = $this->mapping('sales', [
            ['target' => 'name', 'type' => 'string', 'key' => true],
        ]);

        $this->service->sync($mapping);
        $this->service->sync($mapping->fresh());

        $unique = collect(Schema::getIndexes('sales'))
            ->filter(fn (array $index): bool => $index['unique'] && $index['columns'] === ['name'])
            ->count();

        $this->assertSame(1, $unique);
    }

    public function test_it_refuses_to_touch_application_tables(): void
    {
        $mapping = $this->mapping('users', [
            ['target' => 'name', 'type' => 'string'],
        ]);

        $this->expectException(SchemaSyncException::class);
        $this->expectExceptionMessage('belongs to the application');

        $this->service->sync($mapping);
    }

    public function test_it_refuses_identifiers_that_are_not_plain_names(): void
    {
        $mapping = $this->mapping('sales; drop table users', [
            ['target' => 'name', 'type' => 'string'],
        ]);

        $this->expectException(SchemaSyncException::class);

        $this->service->sync($mapping);
    }

    public function test_it_refuses_a_mapping_without_columns(): void
    {
        $mapping = $this->mapping('sales', []);

        $this->expectException(SchemaSyncException::class);
        $this->expectExceptionMessage('no columns configured');

        $this->service->sync($mapping);
    }

    private function mapping(string $table, array $columns): SheetMapping
    {
        $source = SheetSource::create([
            'name' => 'Demo',
            'spreadsheet_id' => 'demo-'.uniqid(),
        ]);

        $tab = SheetTab::create([
            'sheet_source_id' => $source->id,
            'tab_name' => 'Sheet1',
        ]);

        $mapping = SheetMapping::create([
            'sheet_tab_id' => $tab->id,
            'target_table' => $table,
        ]);

        foreach ($columns as $column) {
            $this->addColumn($mapping, $column);
        }

        return $mapping->load('columns');
    }

    private function addColumn(SheetMapping $mapping, array $column): void
    {
        SheetMappingColumn::create([
            'sheet_mapping_id' => $mapping->id,
            'source_column' => $column['target'],
            'target_column' => $column['target'],
            'data_type' => $column['type'],
            'is_nullable' => $column['nullable'] ?? ! ($column['key'] ?? false),
            'is_unique_key' => $column['key'] ?? false,
        ]);
    }
}
