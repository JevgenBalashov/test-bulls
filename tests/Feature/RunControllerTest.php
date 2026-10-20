<?php

namespace Tests\Feature;

use App\Models\ImportRun;
use App\Models\SheetSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RunControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_read_the_journal(): void
    {
        $this->get(route('runs.index'))->assertRedirect(route('login'));
    }

    public function test_index_paginates_the_journal_newest_first(): void
    {
        $source = $this->source();

        foreach (range(1, 21) as $number) {
            $this->importRun($source, [
                'status' => 'success',
                'rows_read' => $number,
                'rows_written' => $number,
            ]);
        }

        $this->login()
            ->get(route('runs.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Runs/Index')
                ->has('runs.data', 20)
                ->where('runs.total', 21)
                ->where('runs.data.0.rows_read', 21)
                ->where('runs.data.0.source.name', 'Sales')
                ->where('runs.data.0.items_count', 0)
            );
    }

    public function test_show_renders_a_run_with_its_items(): void
    {
        $source = $this->source();
        $run = $this->importRun($source, [
            'status' => 'partial',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'rows_read' => 5,
            'rows_written' => 4,
        ]);

        $run->items()->create([
            'tab_name' => 'January',
            'target_table' => 'sales',
            'status' => 'success',
            'rows_read' => 5,
            'rows_written' => 4,
            'rows_skipped' => 1,
            'added_columns' => ['currency'],
        ]);

        $run->items()->create([
            'tab_name' => 'January',
            'target_table' => 'products',
            'status' => 'failed',
            'error_message' => 'The sheet is missing columns: supplier.',
        ]);

        $this->login()
            ->get(route('runs.show', $run))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Runs/Show')
                ->where('run.status', 'partial')
                ->where('run.source.name', 'Sales')
                ->where('run.rows_written', 4)
                ->has('run.items', 2)
                ->where('run.items.0.target_table', 'sales')
                ->where('run.items.0.rows_skipped', 1)
                ->where('run.items.0.added_columns', ['currency'])
                ->where('run.items.1.status', 'failed')
                ->where('run.items.1.added_columns', [])
                ->where('run.items.1.error_message', 'The sheet is missing columns: supplier.')
            );
    }

    private function login(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function source(): SheetSource
    {
        return SheetSource::create([
            'name' => 'Sales',
            'spreadsheet_id' => 'sales-spreadsheet',
        ]);
    }

    private function importRun(SheetSource $source, array $attributes): ImportRun
    {
        return $source->runs()->create($attributes);
    }
}
