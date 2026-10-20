<?php

namespace Tests\Feature;

use App\Jobs\ImportSheetSource;
use App\Models\SheetMapping;
use App\Models\SheetMappingColumn;
use App\Models\SheetSource;
use App\Models\SheetTab;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SourceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Schema::dropIfExists('sales');

        parent::tearDown();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $source = $this->source('Demo', 'demo-spreadsheet');

        $this->get(route('sources.index'))->assertRedirect(route('login'));
        $this->get(route('sources.show', $source))->assertRedirect(route('login'));
    }

    public function test_index_lists_the_configured_sources(): void
    {
        $this->source('Warehouse', 'warehouse-spreadsheet');
        $sales = $this->source('Sales', 'sales-spreadsheet');
        $sales->tabs()->create(['tab_name' => 'January']);

        $this->login()
            ->get(route('sources.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sources/Index')
                ->has('sources', 2)
                ->where('sources.0.name', 'Sales')
                ->where('sources.0.tabs_count', 1)
                ->where('sources.0.latest_run', null)
                ->where('sources.1.name', 'Warehouse')
            );
    }

    public function test_show_renders_the_configuration_tree(): void
    {
        $source = $this->source('Sales', 'sales-spreadsheet');
        $mapping = $this->mapping($source, 'Sheet1', 'sales');
        $this->column($mapping, 'date', 'date', 'date');
        $this->column($mapping, 'product_name', 'name');

        $this->login()
            ->get(route('sources.show', $source))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sources/Show')
                ->where('source.name', 'Sales')
                ->has('tabs', 1)
                ->where('tabs.0.tab_name', 'Sheet1')
                ->where('tabs.0.header_row', 1)
                ->has('tabs.0.mappings', 1)
                ->where('tabs.0.mappings.0.target_table', 'sales')
                ->where('tabs.0.mappings.0.table_exists', false)
                ->has('tabs.0.mappings.0.columns', 2)
                ->where('tabs.0.mappings.0.columns.0.target_column', 'date')
                ->where('tabs.0.mappings.0.columns.0.pending_in_table', false)
                ->has('dataTypes')
                ->has('writeModes')
            );
    }

    public function test_show_flags_configured_columns_that_the_target_table_does_not_have_yet(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        $source = $this->source('Sales', 'sales-spreadsheet');
        $mapping = $this->mapping($source, 'Sheet1', 'sales');
        $this->column($mapping, 'product_name', 'name');
        $this->column($mapping, 'currency', 'currency');

        $this->login()
            ->get(route('sources.show', $source))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tabs.0.mappings.0.table_exists', true)
                ->where('tabs.0.mappings.0.columns.0.pending_in_table', false)
                ->where('tabs.0.mappings.0.columns.1.pending_in_table', true)
            );
    }

    public function test_store_creates_a_source(): void
    {
        $response = $this->login()->post(route('sources.store'), [
            'name' => 'Sales',
            'spreadsheet_id' => 'sales-spreadsheet',
            'is_active' => true,
        ]);

        $source = SheetSource::firstWhere('spreadsheet_id', 'sales-spreadsheet');

        $this->assertNotNull($source);
        $response->assertRedirect(route('sources.show', $source))->assertSessionHas('status');
        $this->assertTrue($source->is_active);
    }

    public function test_store_rejects_a_spreadsheet_that_is_already_configured(): void
    {
        $this->source('Sales', 'sales-spreadsheet');

        $this->login()
            ->post(route('sources.store'), [
                'name' => 'Sales copy',
                'spreadsheet_id' => 'sales-spreadsheet',
            ])
            ->assertSessionHasErrors('spreadsheet_id');

        $this->assertSame(1, SheetSource::count());
    }

    public function test_update_changes_a_source(): void
    {
        $source = $this->source('Sales', 'sales-spreadsheet');

        $this->login()
            ->from(route('sources.show', $source))
            ->put(route('sources.update', $source), [
                'name' => 'Sales 2026',
                'spreadsheet_id' => 'sales-spreadsheet',
                'is_active' => false,
            ])
            ->assertRedirect(route('sources.show', $source));

        $source->refresh();

        $this->assertSame('Sales 2026', $source->name);
        $this->assertFalse($source->is_active);
    }

    public function test_destroy_deletes_a_source_together_with_its_tabs(): void
    {
        $source = $this->source('Sales', 'sales-spreadsheet');
        $this->mapping($source, 'Sheet1', 'sales');

        $this->login()
            ->delete(route('sources.destroy', $source))
            ->assertRedirect(route('sources.index'));

        $this->assertDatabaseCount('sheet_sources', 0);
        $this->assertDatabaseCount('sheet_tabs', 0);
        $this->assertDatabaseCount('sheet_mappings', 0);
    }

    public function test_import_queues_the_job_for_the_source(): void
    {
        Queue::fake();

        $source = $this->source('Sales', 'sales-spreadsheet');

        $this->login()
            ->from(route('sources.show', $source))
            ->post(route('sources.import', $source))
            ->assertRedirect(route('sources.show', $source))
            ->assertSessionHas('status');

        Queue::assertPushed(ImportSheetSource::class, fn (ImportSheetSource $job): bool => $job->source->is($source));
    }

    private function login(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function source(string $name, string $spreadsheetId): SheetSource
    {
        return SheetSource::create([
            'name' => $name,
            'spreadsheet_id' => $spreadsheetId,
        ]);
    }

    private function mapping(SheetSource $source, string $tabName, string $table): SheetMapping
    {
        $tab = SheetTab::create([
            'sheet_source_id' => $source->id,
            'tab_name' => $tabName,
        ]);

        return SheetMapping::create([
            'sheet_tab_id' => $tab->id,
            'target_table' => $table,
        ]);
    }

    private function column(SheetMapping $mapping, string $source, string $target, string $type = 'string'): void
    {
        SheetMappingColumn::create([
            'sheet_mapping_id' => $mapping->id,
            'source_column' => $source,
            'target_column' => $target,
            'data_type' => $type,
        ]);
    }
}
