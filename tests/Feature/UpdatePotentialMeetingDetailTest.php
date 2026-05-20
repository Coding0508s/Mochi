<?php

namespace Tests\Feature;

use App\Actions\UpdatePotentialMeetingDetail;
use App\Models\CoNewTarget;
use App\Models\CoNewTargetDetail;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UpdatePotentialMeetingDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
    }

    public function test_updates_meeting_detail_and_recalculates_year(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);

        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountManager' => '담당A',
            'AccountName' => '기관A',
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'IsContract' => false,
            'created_by' => $user->id,
        ]);

        $detail = CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => '기관A',
            'AccountManager' => '담당A',
            'MeetingDate' => '2026-04-10',
            'MeetingTime' => '09:00',
            'MeetingTime_End' => '10:00',
            'Description' => '기존 내용',
            'ConsultingType' => '기존 유형',
            'Possibility' => 'B',
        ]);

        $updated = app(UpdatePotentialMeetingDetail::class)($target, (int) $detail->ID, [
            'meeting_date' => '2027-05-01',
            'meeting_time' => '11:00',
            'meeting_time_end' => '12:30',
            'description' => '수정된 내용',
            'consulting_type' => '수정 유형',
            'possibility' => 'A',
            'account_manager' => '담당B',
        ]);

        $this->assertSame(2027, (int) $updated->Year);
        $this->assertSame('2027-05-01', $updated->MeetingDate?->format('Y-m-d'));
        $this->assertSame('11:00', $updated->MeetingTime);
        $this->assertSame('12:30', $updated->MeetingTime_End);
        $this->assertSame('수정 유형', $updated->ConsultingType);
        $this->assertSame('A', $updated->Possibility);
        $this->assertSame('수정된 내용', $updated->Description);
        $this->assertSame('담당B', $updated->AccountManager);
    }

    public function test_rejects_contracted_target(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);

        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountManager' => '담당A',
            'AccountName' => '계약기관',
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'IsContract' => true,
            'created_by' => $user->id,
        ]);

        $detail = CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => '계약기관',
            'AccountManager' => '담당A',
            'MeetingDate' => '2026-04-10',
            'ConsultingType' => '유형',
        ]);

        $this->expectException(AuthorizationException::class);

        app(UpdatePotentialMeetingDetail::class)($target, (int) $detail->ID, [
            'meeting_date' => '2026-04-11',
            'consulting_type' => '변경',
        ]);
    }

    public function test_rejects_user_who_cannot_manage_target(): void
    {
        $owner = User::factory()->create(['name' => '소유자', 'is_admin' => false]);
        $other = User::factory()->create(['name' => '다른사람', 'is_admin' => false]);
        $this->actingAs($other);

        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountManager' => '소유자',
            'AccountName' => '권한없음기관',
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'IsContract' => false,
            'created_by' => $owner->id,
        ]);

        $detail = CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => '권한없음기관',
            'AccountManager' => '소유자',
            'MeetingDate' => '2026-04-10',
            'ConsultingType' => '유형',
        ]);

        $this->expectException(AuthorizationException::class);

        app(UpdatePotentialMeetingDetail::class)($target, (int) $detail->ID, [
            'meeting_date' => '2026-04-11',
            'consulting_type' => '변경',
        ]);
    }

    public function test_throws_not_found_when_detail_not_belongs_to_target(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);

        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountManager' => '담당A',
            'AccountName' => '기관A',
            'Type' => '신규(25년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'IsContract' => false,
            'created_by' => $user->id,
        ]);

        $detail = CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => '기관B',
            'AccountManager' => '담당B',
            'MeetingDate' => '2026-04-10',
            'ConsultingType' => '유형',
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(UpdatePotentialMeetingDetail::class)($target, (int) $detail->ID, [
            'meeting_date' => '2026-04-11',
            'consulting_type' => '변경',
        ]);
    }

    private function createTables(): void
    {
        Schema::dropIfExists('S_CO_NewTarget_Detail');
        Schema::dropIfExists('S_CO_NewTarget');

        Schema::create('S_CO_NewTarget', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->date('CreatedDate')->nullable();
            $table->string('AccountManager', 100)->nullable();
            $table->string('AccountName', 150);
            $table->string('Type', 100);
            $table->string('Gubun', 100);
            $table->integer('LS')->default(0);
            $table->integer('GS_K')->default(0);
            $table->integer('GS_E')->default(0);
            $table->integer('Total')->default(0);
            $table->boolean('IsContract')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
        });

        Schema::create('S_CO_NewTarget_Detail', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->string('AccountName', 150);
            $table->string('AccountManager', 100)->nullable();
            $table->date('MeetingDate');
            $table->string('MeetingTime', 20)->nullable();
            $table->string('MeetingTime_End', 20)->nullable();
            $table->text('Description')->nullable();
            $table->string('ConsultingType', 100)->nullable();
            $table->string('Possibility', 20)->nullable();
        });
    }
}
