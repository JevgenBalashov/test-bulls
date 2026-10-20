<?php

namespace Tests\Feature;

use App\Models\SheetMapping;
use App\Models\SheetMappingColumn;
use App\Models\SheetSource;
use App\Models\SheetTab;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MappingColumnControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_adds_a_column_to_the_mapping(): void
    {
        $mapping = $this->mapping();

        $this->login()
            ->from(route('sources.show', $mapping->tab->sheet_source_id))
            ->post(route('columns.store', $mapping), [
                'source_column' => 'Дата продажи',
                'target_column' => 'sold_at',
                'data_type' => 'date',
                'is_nullable' => false,
                'is_unique_key' => true,
            ])
            ->assertRedirect(route('sources.show', $mapping->tab->sheet_source_id))
            ->assertSessionHas('status');

        $column = $mapping->columns()->sole();

        $this->assertSame('Дата продажи', $column->source_column);
        $this->assertSame('sold_at', $column->target_column);
        $this->assertSame('date', $column->data_type);
        $this->assertFalse($column->is_nullable);
        $this->assertTrue($column->is_unique_key);
    }

    public function test_store_rejects_a_target_column_already_used_in_the_mapping(): void
    {
        $mapping = $this->mapping();
        $this->column($mapping, 'product_name', 'name');

        $this->login()
            ->post(route('columns.store', $mapping), [
                'source_column' => 'Name',
                'target_column' => 'name',
                'data_type' => 'string',
            ])
            ->assertSessionHasErrors('target_column');

        $this->assertSame(1, $mapping->columns()->count());
    }

    public function test_store_rejects_columns_reserved_by_the_importer(): void
    {
        $mapping = $this->mapping();
        $this->login();

        foreach (['id', 'imported_at', 'created_at', 'updated_at'] as $target) {
            $this->post(route('columns.store', $mapping), [
                'source_column' => 'Name',
                'target_column' => $target,
                'data_type' => 'string',
            ])->assertSessionHasErrors('target_column');
        }

        $this->assertSame(0, $mapping->columns()->count());
    }

    public function test_store_rejects_a_data_type_the_importer_cannot_cast(): void
    {
        $mapping = $this->mapping();

        $this->login()
            ->post(route('columns.store', $mapping), [
                'source_column' => 'Price',
                'target_column' => 'price',
                'data_type' => 'money',
            ])
            ->assertSessionHasErrors('data_type');

        $this->assertSame(0, $mapping->columns()->count());
    }

    public function test_update_changes_a_column(): void
    {
        $mapping = $this->mapping();
        $column = $this->column($mapping, 'price', 'price');

        $this->login()
            ->from(route('sources.show', $mapping->tab->sheet_source_id))
            ->patch(route('columns.update', $column), [
                'source_column' => 'Price, EUR',
                'target_column' => 'price_eur',
                'data_type' => 'decimal',
                'is_nullable' => true,
                'is_unique_key' => false,
            ])
            ->assertRedirect(route('sources.show', $mapping->tab->sheet_source_id));

        $column->refresh();

        $this->assertSame('Price, EUR', $column->source_column);
        $this->assertSame('price_eur', $column->target_column);
        $this->assertSame('decimal', $column->data_type);
    }

    public function test_destroy_deletes_a_column(): void
    {
        $mapping = $this->mapping();
        $column = $this->column($mapping, 'price', 'price');

        $this->login()
            ->from(route('sources.show', $mapping->tab->sheet_source_id))
            ->delete(route('columns.destroy', $column))
            ->assertRedirect(route('sources.show', $mapping->tab->sheet_source_id));

        $this->assertDatabaseCount('sheet_mapping_columns', 0);
    }

    private function login(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function mapping(): SheetMapping
    {
        $source = SheetSource::create([
            'name' => 'Sales',
            'spreadsheet_id' => 'sales-spreadsheet',
        ]);

        $tab = SheetTab::create([
            'sheet_source_id' => $source->id,
            'tab_name' => 'January',
        ]);

        return SheetMapping::create([
            'sheet_tab_id' => $tab->id,
            'target_table' => 'sales',
        ]);
    }

    private function column(SheetMapping $mapping, string $source, string $target): SheetMappingColumn
    {
        return SheetMappingColumn::create([
            'sheet_mapping_id' => $mapping->id,
            'source_column' => $source,
            'target_column' => $target,
            'data_type' => 'string',
        ]);
    }
}
