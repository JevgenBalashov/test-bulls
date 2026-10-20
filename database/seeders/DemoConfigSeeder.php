<?php

namespace Database\Seeders;

use App\Models\SheetMapping;
use App\Models\SheetMappingColumn;
use App\Models\SheetSource;
use App\Models\SheetTab;
use Illuminate\Database\Seeder;

class DemoConfigSeeder extends Seeder
{
    public function run(): void
    {
        $euTab = $this->tab('Sales Report EU', 'DEMO_SPREADSHEET_ID_EU', 'Daily Sales');

        $this->mapping($euTab, 'sales', [
            ['source' => 'date', 'target' => 'date', 'type' => 'date', 'key' => true],
            ['source' => 'product_name', 'target' => 'name', 'type' => 'string', 'key' => true],
            ['source' => 'price', 'target' => 'product_price', 'type' => 'decimal'],
            ['source' => 'amount', 'target' => 'amount', 'type' => 'integer'],
        ]);

        $this->mapping($euTab, 'products', [
            ['source' => 'product_name', 'target' => 'name', 'type' => 'string', 'key' => true],
            ['source' => 'price', 'target' => 'last_price', 'type' => 'decimal'],
        ]);

        $usTab = $this->tab('Sales Report US', 'DEMO_SPREADSHEET_ID_US', 'Sales 2026');

        $this->mapping($usTab, 'sales', [
            ['source' => 'Amount', 'target' => 'amount', 'type' => 'integer'],
            ['source' => 'Date', 'target' => 'date', 'type' => 'date', 'key' => true],
            ['source' => 'Price', 'target' => 'product_price', 'type' => 'decimal'],
            ['source' => 'Product', 'target' => 'name', 'type' => 'string', 'key' => true],
            ['source' => 'Currency', 'target' => 'currency', 'type' => 'string'],
            ['source' => 'Manager', 'target' => 'manager_name', 'type' => 'string'],
        ]);
    }

    private function tab(string $sourceName, string $spreadsheetId, string $tabName): SheetTab
    {
        $source = SheetSource::updateOrCreate(
            ['spreadsheet_id' => $spreadsheetId],
            ['name' => $sourceName, 'is_active' => true],
        );

        return SheetTab::updateOrCreate(
            ['sheet_source_id' => $source->id, 'tab_name' => $tabName],
            ['header_row' => 1, 'data_start_row' => 2, 'is_active' => true],
        );
    }

    private function mapping(SheetTab $tab, string $targetTable, array $columns): void
    {
        $mapping = SheetMapping::updateOrCreate(
            ['sheet_tab_id' => $tab->id, 'target_table' => $targetTable],
            ['write_mode' => 'upsert', 'is_active' => true],
        );

        foreach ($columns as $column) {
            $isKey = $column['key'] ?? false;

            SheetMappingColumn::updateOrCreate(
                ['sheet_mapping_id' => $mapping->id, 'target_column' => $column['target']],
                [
                    'source_column' => $column['source'],
                    'data_type' => $column['type'],
                    'is_nullable' => ! $isKey,
                    'is_unique_key' => $isKey,
                ],
            );
        }
    }
}
