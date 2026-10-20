<?php

namespace Tests\Feature;

use App\Models\SheetSource;
use App\Models\SheetTab;
use App\Models\User;
use App\Services\GoogleSheets\GoogleSheetsClient;
use App\Services\GoogleSheets\SheetData;
use App\Services\GoogleSheets\SheetReadException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class TabControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_adds_a_tab_to_the_source(): void
    {
        $source = $this->source('sales-spreadsheet');

        $this->login()
            ->from(route('sources.show', $source))
            ->post(route('tabs.store', $source), [
                'tab_name' => 'January',
                'header_row' => 2,
                'data_start_row' => 4,
                'is_active' => true,
            ])
            ->assertRedirect(route('sources.show', $source))
            ->assertSessionHas('status');

        $tab = $source->tabs()->sole();

        $this->assertSame('January', $tab->tab_name);
        $this->assertSame(2, $tab->header_row);
        $this->assertSame(4, $tab->data_start_row);
    }

    public function test_store_rejects_a_tab_name_already_used_in_the_same_source(): void
    {
        $source = $this->source('sales-spreadsheet');
        $this->tab($source, 'January');

        $this->login()
            ->post(route('tabs.store', $source), [
                'tab_name' => 'January',
                'header_row' => 1,
                'data_start_row' => 2,
            ])
            ->assertSessionHasErrors('tab_name');

        $this->assertSame(1, $source->tabs()->count());
    }

    public function test_store_allows_the_same_tab_name_under_another_source(): void
    {
        $sales = $this->source('sales-spreadsheet');
        $warehouse = $this->source('warehouse-spreadsheet');
        $this->tab($sales, 'January');

        $this->login()
            ->post(route('tabs.store', $warehouse), [
                'tab_name' => 'January',
                'header_row' => 1,
                'data_start_row' => 2,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $warehouse->tabs()->count());
    }

    public function test_store_rejects_a_data_start_row_that_does_not_follow_the_header_row(): void
    {
        $source = $this->source('sales-spreadsheet');

        $this->login()
            ->post(route('tabs.store', $source), [
                'tab_name' => 'January',
                'header_row' => 3,
                'data_start_row' => 3,
            ])
            ->assertSessionHasErrors('data_start_row');

        $this->assertSame(0, $source->tabs()->count());
    }

    public function test_update_changes_a_tab(): void
    {
        $source = $this->source('sales-spreadsheet');
        $tab = $this->tab($source, 'January');

        $this->login()
            ->from(route('sources.show', $source))
            ->patch(route('tabs.update', $tab), [
                'tab_name' => 'February',
                'header_row' => 1,
                'data_start_row' => 3,
                'is_active' => false,
            ])
            ->assertRedirect(route('sources.show', $source));

        $tab->refresh();

        $this->assertSame('February', $tab->tab_name);
        $this->assertSame(3, $tab->data_start_row);
        $this->assertFalse($tab->is_active);
    }

    public function test_destroy_deletes_a_tab(): void
    {
        $source = $this->source('sales-spreadsheet');
        $tab = $this->tab($source, 'January');

        $this->login()
            ->from(route('sources.show', $source))
            ->delete(route('tabs.destroy', $tab))
            ->assertRedirect(route('sources.show', $source));

        $this->assertDatabaseCount('sheet_tabs', 0);
    }

    public function test_headers_returns_the_column_names_of_the_sheet(): void
    {
        $source = $this->source('sales-spreadsheet');
        $tab = $this->tab($source, 'January');

        $this->mock(GoogleSheetsClient::class, function (MockInterface $mock) {
            $mock->shouldReceive('readTab')
                ->once()
                ->with('sales-spreadsheet', 'January', 1, 2)
                ->andReturn(new SheetData(['date', 'product_name'], [
                    ['date' => '2026-01-05', 'product_name' => 'Keyboard'],
                ]));
        });

        $this->login()
            ->get(route('tabs.headers', $tab))
            ->assertOk()
            ->assertExactJson([
                'headers' => ['date', 'product_name'],
                'rows' => 1,
            ]);
    }

    public function test_headers_reports_a_sheet_that_cannot_be_read(): void
    {
        $source = $this->source('sales-spreadsheet');
        $tab = $this->tab($source, 'January');

        $this->mock(GoogleSheetsClient::class, function (MockInterface $mock) {
            $mock->shouldReceive('readTab')
                ->once()
                ->andThrow(SheetReadException::tabNotFound('January', 'sales-spreadsheet'));
        });

        $this->login()
            ->get(route('tabs.headers', $tab))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tab [January] was not found in spreadsheet [sales-spreadsheet].');
    }

    private function login(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function source(string $spreadsheetId): SheetSource
    {
        return SheetSource::create([
            'name' => 'Sales',
            'spreadsheet_id' => $spreadsheetId,
        ]);
    }

    private function tab(SheetSource $source, string $name): SheetTab
    {
        return SheetTab::create([
            'sheet_source_id' => $source->id,
            'tab_name' => $name,
        ]);
    }
}
