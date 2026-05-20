<?php

namespace Tests\Feature;

use App\Actions\UpdatePotentialInstitution;
use App\Models\CoNewTarget;
use App\Models\CoNewTargetDetail;
use App\Models\SupportRecord;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UpdatePotentialInstitutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
    }

    public function test_updates_master_fields_and_recalculates_total(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);

        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountManager' => '담당A',
            'AccountName' => '수정전기관',
            'Type' => '신규(24년)',
            'Gubun' => '신규기관방문',
            'Director' => '원장A',
            'Phone' => '010-1111-2222',
            'Address' => '서울',
            'Connected' => '소개',
            'Possibility' => 'B',
            'LS' => 1,
            'GS_K' => 2,
            'GS_E' => 3,
            'Total' => 6,
            'IsContract' => false,
            'created_by' => $user->id,
        ]);

        $updated = app(UpdatePotentialInstitution::class)($target, [
            'account_manager' => '담당B',
            'type' => '신규(25년)',
            'gubun' => '해지방문',
            'account_name' => '수정후기관',
            'director' => '원장B',
            'phone' => '010-9999-8888',
            'address' => '부산',
            'connected' => '지인',
            'possibility' => 'A',
            'ls' => 4,
            'gs_k' => 5,
            'gs_e' => 6,
        ]);

        $this->assertSame('담당B', $updated->AccountManager);
        $this->assertSame('수정후기관', $updated->AccountName);
        $this->assertSame(15, (int) $updated->Total);
        $this->assertSame(4, (int) $updated->LS);
        $this->assertSame(5, (int) $updated->GS_K);
        $this->assertSame(6, (int) $updated->GS_E);
    }

    public function test_renames_account_and_syncs_details_and_support_records(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);

        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountManager' => '담당A',
            'AccountName' => '동기화전',
            'Type' => '신규(24년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'IsContract' => false,
            'created_by' => $user->id,
        ]);

        CoNewTargetDetail::query()->create([
            'Year' => 2026,
            'AccountName' => '동기화전',
            'AccountManager' => '담당A',
            'MeetingDate' => '2026-04-10',
            'ConsultingType' => '콜',
        ]);

        SupportRecord::query()->create([
            'Year' => 2026,
            'potential_target_id' => $target->ID,
            'Account_Name' => '동기화전',
            'Support_Date' => '2026-04-09',
            'Status' => '진행중',
        ]);

        app(UpdatePotentialInstitution::class)($target, [
            'account_manager' => '담당A',
            'type' => '신규(24년)',
            'gubun' => '신규기관방문',
            'account_name' => '동기화후',
            'ls' => 0,
            'gs_k' => 0,
            'gs_e' => 0,
        ]);

        $this->assertDatabaseHas('S_CO_NewTarget_Detail', [
            'AccountName' => '동기화후',
            'AccountManager' => '담당A',
        ]);
        $this->assertDatabaseMissing('S_CO_NewTarget_Detail', [
            'AccountName' => '동기화전',
        ]);
        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'potential_target_id' => $target->ID,
            'Account_Name' => '동기화후',
        ]);
    }

    public function test_rejects_contracted_target(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);

        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountName' => '계약기관',
            'Type' => '신규(24년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'IsContract' => true,
            'created_by' => $user->id,
        ]);

        $this->expectException(AuthorizationException::class);

        app(UpdatePotentialInstitution::class)($target, [
            'type' => '신규(25년)',
            'gubun' => '신규기관방문',
            'account_name' => '변경시도',
            'ls' => 0,
            'gs_k' => 0,
            'gs_e' => 0,
        ]);
    }

    public function test_rejects_user_who_cannot_manage_target(): void
    {
        $owner = User::factory()->create(['name' => '소유자', 'is_admin' => false]);
        $other = User::factory()->create(['name' => '타인', 'is_admin' => false]);
        $this->actingAs($other);

        $target = CoNewTarget::query()->create([
            'Year' => 2026,
            'CreatedDate' => '2026-04-01',
            'AccountManager' => '소유자',
            'AccountName' => '권한없음',
            'Type' => '신규(24년)',
            'Gubun' => '신규기관방문',
            'LS' => 0,
            'GS_K' => 0,
            'GS_E' => 0,
            'Total' => 0,
            'IsContract' => false,
            'created_by' => $owner->id,
        ]);

        $this->expectException(AuthorizationException::class);

        app(UpdatePotentialInstitution::class)($target, [
            'type' => '신규(25년)',
            'gubun' => '신규기관방문',
            'account_name' => '변경시도',
            'ls' => 0,
            'gs_k' => 0,
            'gs_e' => 0,
        ]);
    }

    private function createTables(): void
    {
        Schema::dropIfExists('S_CO_NewTarget_Detail');
        Schema::dropIfExists('S_CO_NewTarget');
        Schema::dropIfExists('S_SupportInfo_Account');

        Schema::create('S_SupportInfo_Account', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->unsignedInteger('potential_target_id')->nullable();
            $table->string('Account_Name', 255)->nullable();
            $table->date('Support_Date')->nullable();
            $table->string('Status', 50)->nullable();
        });

        Schema::create('S_CO_NewTarget', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->date('CreatedDate')->nullable();
            $table->string('AccountManager', 100)->nullable();
            $table->string('AccountCode', 100)->nullable();
            $table->string('AccountName', 150);
            $table->string('Address', 255)->nullable();
            $table->string('Director', 100)->nullable();
            $table->string('Phone', 50)->nullable();
            $table->string('Connected', 100)->nullable();
            $table->string('Type', 100);
            $table->string('Gubun', 100);
            $table->integer('LS')->default(0);
            $table->integer('GS_K')->default(0);
            $table->integer('GS_E')->default(0);
            $table->integer('Total')->default(0);
            $table->boolean('IsContract')->default(false);
            $table->string('Possibility', 20)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
        });

        Schema::create('S_CO_NewTarget_Detail', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->string('AccountName', 150);
            $table->string('AccountManager', 100)->nullable();
            $table->date('MeetingDate');
            $table->string('ConsultingType', 100)->nullable();
        });
    }
}
