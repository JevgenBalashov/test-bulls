<?php

namespace Tests\Feature;

use App\Models\SheetMapping;
use App\Models\SheetMappingColumn;
use App\Models\SheetSource;
use App\Models\SheetTab;
use App\Models\User;
use App\Services\Schema\SchemaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MappingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_adds_a_target_table_to_the_tab(): void
    {
        $tab = $this->tab();

        $this->login()
            ->from(route('sources.show', $tab->sheet_source_id))
            ->post(route('mappings.store', $tab), [
                'target_table' => 'sales',
                'write_mode' => 'upsert',
                'is_active' => true,
            ])
            ->assertRedirect(route('sources.show', $tab->sheet_source_id))
            ->assertSessionHas('status');

        $mapping = $tab->mappings()->sole();

        $this->assertSame('sales', $mapping->target_table);
        $this->assertSame('upsert', $mapping->write_mode);
    }

    public function test_store_rejects_a_table_owned_by_the_application(): void
    {
        $tab = $this->tab();
        $this->login();

        foreach (['users', 'sheet_sources', 'import_runs'] as $table) {
            $this->post(route('mappings.store', $tab), [
                'target_table' => $table,
                'write_mode' => 'upsert',
            ])->assertSessionHasErrors('target_table');
        }

        $this->assertContains('sheet_sources', SchemaSyncService::RESERVED_TABLES);
        $this->assertSame(0, $tab->mappings()->count());
    }

    public function test_store_rejects_a_table_name_with_unsupported_characters(): void
    {
        $tab = $this->tab();
        $this->login();

        foreach (['sales 2026', 'sales-2026', 'sales;drop', '2026_sales'] as $table) {
            $this->post(route('mappings.store', $tab), [
                'target_table' => $table,
                'write_mode' => 'upsert',
            ])->assertSessionHasErrors('target_table');
        }

        $this->assertSame(0, $tab->mappings()->count());
    }

    public function test_store_rejects_the_same_target_table_twice_on_one_tab(): void
    {
        $tab = $this->tab();
        $this->mapping($tab, 'sales');

        $this->login()
            ->post(route('mappings.store', $tab), [
                'target_table' => 'sales',
                'write_mode' => 'upsert',
            ])
            ->assertSessionHasErrors('target_table');

        $this->assertSame(1, $tab->mappings()->count());
    }

    public function test_update_changes_a_mapping(): void
    {
        $tab = $this->tab();
        $mapping = $this->mapping($tab, 'sales');

        $this->login()
            ->from(route('sources.show', $tab->sheet_source_id))
            ->patch(route('mappings.update', $mapping), [
                'target_table' => 'sales_2026',
                'write_mode' => 'insert',
                'is_active' => false,
            ])
            ->assertRedirect(route('sources.show', $tab->sheet_source_id));

        $mapping->refresh();

        $this->assertSame('sales_2026', $mapping->target_table);
        $this->assertSame('insert', $mapping->write_mode);
        $this->assertFalse($mapping->is_active);
    }

    public function test_destroy_removes_the_mapping_with_its_columns(): void
    {
        $tab = $this->tab();
        $mapping = $this->mapping($tab, 'sales');

        SheetMappingColumn::create([
            'sheet_mapping_id' => $mapping->id,
            'source_column' => 'product_name',
            'target_column' => 'name',
            'data_type' => 'string',
        ]);

        $this->login()
            ->from(route('sources.show', $tab->sheet_source_id))
            ->delete(route('mappings.destroy', $mapping))
            ->assertRedirect(route('sources.show', $tab->sheet_source_id));

        $this->assertDatabaseCount('sheet_mappings', 0);
        $this->assertDatabaseCount('sheet_mapping_columns', 0);
    }

    private function login(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function tab(): SheetTab
    {
        $source = SheetSource::create([
            'name' => 'Sales',
            'spreadsheet_id' => 'sales-spreadsheet',
        ]);

        return SheetTab::create([
            'sheet_source_id' => $source->id,
            'tab_name' => 'January',
        ]);
    }

    private function mapping(SheetTab $tab, string $table): SheetMapping
    {
        return SheetMapping::create([
            'sheet_tab_id' => $tab->id,
            'target_table' => $table,
        ]);
    }
}
