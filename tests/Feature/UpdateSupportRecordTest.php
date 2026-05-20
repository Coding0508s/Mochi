<?php

namespace Tests\Feature;

use App\Actions\UpdateSupportRecord;
use App\Models\SupportRecord;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UpdateSupportRecordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
    }

    public function test_author_can_update_all_fields(): void
    {
        $user = User::factory()->create(['name' => '홍길동', 'is_admin' => false]);
        $this->actingAs($user);

        $record = SupportRecord::query()->create([
            'Year' => 2026,
            'Support_Date' => '2026-04-01',
            'Meet_Time' => '09:00:00',
            'Support_Type' => '방문',
            'Target' => '원장',
            'Issue' => '기존 이슈',
            'TO_Account' => '기존 소통',
            'TO_Depart' => '기존 본사',
            'Others' => '기존 기타',
            'Status' => '진행중',
            'SK_Code' => 'SK-UPD',
            'Account_Name' => '테스트기관',
            'TR_Name' => '홍길동',
        ]);

        $updated = app(UpdateSupportRecord::class)($record, 'SK-UPD', [
            'support_date' => '2026-05-10',
            'support_time' => '14:30',
            'support_type' => '전화',
            'target' => '교사',
            'issue' => '수정 이슈',
            'to_account' => '수정 소통',
            'to_depart' => '수정 본사',
            'others' => '수정 기타',
            'completed' => true,
        ]);

        $this->assertSame(2026, (int) $updated->Year);
        $this->assertSame('2026-05-10', $updated->Support_Date?->format('Y-m-d'));
        $this->assertStringContainsString('14:30', (string) $updated->Meet_Time);
        $this->assertSame('전화', $updated->Support_Type);
        $this->assertSame('교사', $updated->Target);
        $this->assertSame('수정 이슈', $updated->Issue);
        $this->assertSame('수정 소통', $updated->TO_Account);
        $this->assertSame('수정 본사', $updated->TO_Depart);
        $this->assertSame('수정 기타', $updated->Others);
        $this->assertSame('완료', $updated->Status);
        $this->assertNotNull($updated->CompletedDate);
    }

    public function test_rejects_non_author(): void
    {
        $author = User::factory()->create(['name' => '작성자', 'is_admin' => false]);
        $other = User::factory()->create(['name' => '타인', 'is_admin' => false]);
        $this->actingAs($other);

        $record = SupportRecord::query()->create([
            'Year' => 2026,
            'Support_Date' => '2026-04-01',
            'Support_Type' => '방문',
            'SK_Code' => 'SK-UPD',
            'Account_Name' => '테스트기관',
            'TR_Name' => '작성자',
        ]);

        $this->expectException(AuthorizationException::class);

        app(UpdateSupportRecord::class)($record, 'SK-UPD', [
            'support_date' => '2026-05-10',
            'support_time' => '10:00',
            'support_type' => '전화',
            'completed' => false,
        ]);
    }

    public function test_rejects_sk_code_scope_mismatch(): void
    {
        $user = User::factory()->create(['name' => '홍길동', 'is_admin' => false]);
        $this->actingAs($user);

        $record = SupportRecord::query()->create([
            'Year' => 2026,
            'Support_Date' => '2026-04-01',
            'Support_Type' => '방문',
            'SK_Code' => 'SK-OTHER',
            'Account_Name' => '테스트기관',
            'TR_Name' => '홍길동',
        ]);

        $this->expectException(AuthorizationException::class);

        app(UpdateSupportRecord::class)($record, 'SK-UPD', [
            'support_date' => '2026-05-10',
            'support_time' => '10:00',
            'support_type' => '전화',
            'completed' => false,
        ]);
    }

    public function test_rejects_terminated_institution(): void
    {
        $user = User::factory()->create(['name' => '홍길동', 'is_admin' => false]);
        $this->actingAs($user);

        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
        });

        Schema::create('S_Account_Information', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100);
            $table->string('Account_Name', 255)->nullable();
            $table->string('Customer_Type', 255)->nullable();
        });

        \DB::table('S_AccountName')->insert([
            'SKcode' => 'SK-TERM',
            'AccountName' => '해지기관',
        ]);

        \DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-TERM',
            'Account_Name' => '해지기관',
            'Customer_Type' => '해지',
        ]);

        $record = SupportRecord::query()->create([
            'Year' => 2026,
            'Support_Date' => '2026-04-01',
            'Support_Type' => '방문',
            'SK_Code' => 'SK-TERM',
            'Account_Name' => '해지기관',
            'TR_Name' => '홍길동',
        ]);

        $this->expectException(AuthorizationException::class);

        app(UpdateSupportRecord::class)($record, 'SK-TERM', [
            'support_date' => '2026-05-10',
            'support_time' => '10:00',
            'support_type' => '전화',
            'completed' => false,
        ]);
    }

    private function createTables(): void
    {
        Schema::create('S_SupportInfo_Account', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR_Name', 255)->nullable();
            $table->string('Support_Date', 50)->nullable();
            $table->string('Meet_Time', 50)->nullable();
            $table->string('Support_Type', 100)->nullable();
            $table->string('Target', 255)->nullable();
            $table->text('Issue')->nullable();
            $table->text('TO_Account')->nullable();
            $table->text('TO_Depart')->nullable();
            $table->text('Others')->nullable();
            $table->string('Status', 50)->nullable();
            $table->timestamp('CompletedDate')->nullable();
        });
    }
}
