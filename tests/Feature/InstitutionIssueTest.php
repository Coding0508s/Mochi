<?php

namespace Tests\Feature;

use App\Livewire\InstitutionIssueList;
use App\Livewire\SupportList;
use App\Models\Institution;
use App\Models\SupportRecord;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class InstitutionIssueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSupportTables();
    }

    private function createSupportTables(): void
    {
        Schema::dropIfExists('S_SupportInfo_Account');
        Schema::dropIfExists('S_Account_Information');
        Schema::dropIfExists('S_AccountName');

        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
            $table->string('Address', 500)->nullable();
        });

        Schema::create('S_Account_Information', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100);
            $table->string('Account_Name', 255)->nullable();
            $table->string('CO', 255)->nullable();
            $table->string('TR', 255)->nullable();
            $table->string('CS', 255)->nullable();
            $table->string('Customer_Type', 255)->nullable();
            $table->string('Affiliate', 255)->nullable();
            $table->string('Address', 500)->nullable();
        });

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
            $table->string('Status', 50)->nullable();
            $table->timestamp('CompletedDate')->nullable();
            $table->timestamp('CreatedDate')->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->string('record_kind', 20)->nullable();
        });

        Schema::dropIfExists('urgent_support_notifications');
        Schema::create('urgent_support_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('support_record_id');
            $table->unsignedBigInteger('recipient_user_id');
            $table->unsignedBigInteger('sender_user_id');
            $table->string('sk_code', 20)->nullable();
            $table->string('account_name')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_issue_is_excluded_from_support_list_but_shown_in_issue_list(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-ISSUE-3',
            'AccountName' => '구분 기관',
        ]);

        SupportRecord::query()->create([
            'Year' => (int) now()->format('Y'),
            'SK_Code' => 'SK-ISSUE-3',
            'Account_Name' => '구분 기관',
            'TR_Name' => 'CS담당',
            'Support_Date' => now()->format('Y-m-d'),
            'Meet_Time' => '13:00:00',
            'Support_Type' => '기관이슈',
            'Issue' => '분리 확인용 이슈',
            'record_kind' => SupportRecord::KIND_ISSUE,
            'CreatedDate' => now(),
        ]);

        $user = User::factory()->create(['team' => 'CS']);

        Livewire::actingAs($user)
            ->test(InstitutionIssueList::class)
            ->assertSee('분리 확인용 이슈');

        Livewire::actingAs($user)
            ->test(SupportList::class)
            ->assertDontSee('분리 확인용 이슈');
    }

    public function test_route_is_readable_for_all_authenticated_teams(): void
    {
        $cs = User::factory()->create(['team' => 'CS']);
        $co = User::factory()->create(['team' => 'CO']);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($cs)->get('/institution-issues')->assertOk();
        $this->actingAs($admin)->get('/institution-issues')->assertOk();
        $this->actingAs($co)->get('/institution-issues?team_menu=cs')->assertOk();
    }

    public function test_cs_sidebar_does_not_show_issue_menu(): void
    {
        $cs = User::factory()->create(['team' => 'CS']);

        $this->actingAs($cs)
            ->get('/institutions')
            ->assertOk()
            ->assertDontSee('기관이슈');
    }
}
