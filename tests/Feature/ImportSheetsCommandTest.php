<?php

namespace Tests\Feature;

use App\Jobs\ImportSheetSource;
use App\Models\SheetSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportSheetsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_a_job_for_every_active_source(): void
    {
        Queue::fake();

        $this->source('Active EU', 'eu');
        $this->source('Active US', 'us');

        $this->artisan('sheets:import')->assertSuccessful();

        Queue::assertPushed(ImportSheetSource::class, 2);
    }

    public function test_it_leaves_inactive_sources_alone(): void
    {
        Queue::fake();

        $this->source('Active', 'eu');
        $this->source('Retired', 'old', isActive: false);

        $this->artisan('sheets:import')->assertSuccessful();

        Queue::assertPushed(ImportSheetSource::class, 1);
    }

    public function test_it_can_be_limited_to_a_single_spreadsheet(): void
    {
        Queue::fake();

        $this->source('Active EU', 'eu');
        $us = $this->source('Active US', 'us');

        $this->artisan('sheets:import', ['--source' => ['us']])->assertSuccessful();

        Queue::assertPushed(
            ImportSheetSource::class,
            fn (ImportSheetSource $job) => $job->source->is($us),
        );
        Queue::assertPushed(ImportSheetSource::class, 1);
    }

    public function test_it_warns_instead_of_queueing_when_nothing_matches(): void
    {
        Queue::fake();

        $this->artisan('sheets:import')
            ->expectsOutputToContain('No active sources matched.')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    private function source(string $name, string $spreadsheetId, bool $isActive = true): SheetSource
    {
        return SheetSource::create([
            'name' => $name,
            'spreadsheet_id' => $spreadsheetId,
            'is_active' => $isActive,
        ]);
    }
}
