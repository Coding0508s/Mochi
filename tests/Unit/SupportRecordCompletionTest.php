<?php

namespace Tests\Unit;

use App\Models\SupportRecord;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SupportRecordCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSupportTable();
    }

    public function test_is_completed_when_legacy_status_only(): void
    {
        $record = SupportRecord::query()->create([
            'SK_Code' => 'SK-1',
            'Status' => SupportRecord::STATUS_COMPLETED,
            'CompletedDate' => null,
        ]);

        $this->assertTrue($record->isCompleted());
    }

    public function test_is_completed_when_completed_date_only(): void
    {
        $record = SupportRecord::query()->create([
            'SK_Code' => 'SK-2',
            'Status' => SupportRecord::STATUS_IN_PROGRESS,
            'CompletedDate' => now(),
        ]);

        $this->assertTrue($record->isCompleted());
    }

    public function test_is_not_completed_when_both_fields_empty(): void
    {
        $record = SupportRecord::query()->create([
            'SK_Code' => 'SK-3',
            'Status' => SupportRecord::STATUS_IN_PROGRESS,
            'CompletedDate' => null,
        ]);

        $this->assertFalse($record->isCompleted());
    }

    public function test_completed_scope_includes_legacy_status_rows(): void
    {
        SupportRecord::query()->create([
            'SK_Code' => 'SK-A',
            'Status' => SupportRecord::STATUS_COMPLETED,
            'CompletedDate' => null,
        ]);

        SupportRecord::query()->create([
            'SK_Code' => 'SK-B',
            'Status' => SupportRecord::STATUS_IN_PROGRESS,
            'CompletedDate' => null,
        ]);

        $this->assertSame(1, SupportRecord::query()->completed()->count());
        $this->assertSame('SK-A', (string) SupportRecord::query()->completed()->value('SK_Code'));
    }

    public function test_in_progress_scope_excludes_legacy_completed_rows(): void
    {
        SupportRecord::query()->create([
            'SK_Code' => 'SK-A',
            'Status' => SupportRecord::STATUS_COMPLETED,
            'CompletedDate' => null,
        ]);

        SupportRecord::query()->create([
            'SK_Code' => 'SK-B',
            'Status' => SupportRecord::STATUS_IN_PROGRESS,
            'CompletedDate' => null,
        ]);

        $this->assertSame(1, SupportRecord::query()->inProgress()->count());
        $this->assertSame('SK-B', (string) SupportRecord::query()->inProgress()->value('SK_Code'));
    }

    public function test_toggle_complete_syncs_status_and_completed_date(): void
    {
        $record = SupportRecord::query()->create([
            'SK_Code' => 'SK-T',
            'Status' => SupportRecord::STATUS_COMPLETED,
            'CompletedDate' => null,
        ]);

        $record->toggleComplete(false);
        $record->refresh();

        $this->assertFalse($record->isCompleted());
        $this->assertSame(SupportRecord::STATUS_IN_PROGRESS, $record->Status);
        $this->assertNull($record->CompletedDate);

        $record->toggleComplete(true);
        $record->refresh();

        $this->assertTrue($record->isCompleted());
        $this->assertSame(SupportRecord::STATUS_COMPLETED, $record->Status);
        $this->assertNotNull($record->CompletedDate);
    }

    private function createSupportTable(): void
    {
        Schema::dropIfExists('S_SupportInfo_Account');

        Schema::create('S_SupportInfo_Account', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100)->nullable();
            $table->string('Status', 50)->nullable();
            $table->timestamp('CompletedDate')->nullable();
        });
    }
}
