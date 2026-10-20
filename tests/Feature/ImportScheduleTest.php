<?php

namespace Tests\Feature;

use App\Jobs\ImportSheetSource;
use App\Models\SheetSource;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ImportScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_import_runs_once_a_day(): void
    {
        $event = $this->scheduledImport();

        $this->assertNotNull($event, 'sheets:import is not scheduled.');
        $this->assertSame('0 3 * * *', $event->expression);
    }

    public function test_a_slow_import_cannot_overlap_the_next_one(): void
    {
        $this->assertTrue($this->scheduledImport()->withoutOverlapping);
    }

    public function test_a_failed_job_closes_the_run_it_left_behind(): void
    {
        $source = SheetSource::create([
            'name' => 'Demo',
            'spreadsheet_id' => 'demo',
        ]);

        $run = $source->runs()->create([
            'status' => 'running',
            'started_at' => now(),
        ]);

        (new ImportSheetSource($source))->failed(new RuntimeException('Worker timed out'));

        $run->refresh();

        $this->assertSame('failed', $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertSame('Worker timed out', $run->error_message);
    }

    private function scheduledImport(): ?Event
    {
        return collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains($event->command ?? '', 'sheets:import'));
    }
}
