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

    public function test_urgent_issue_appears_on_support_list_only_when_urgent_filter_on(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-URG-ISSUE',
            'AccountName' => '긴급이슈기관',
        ]);

        SupportRecord::query()->create([
            'Year' => (int) now()->format('Y'),
            'SK_Code' => 'SK-URG-ISSUE',
            'Account_Name' => '긴급이슈기관',
            'TR_Name' => 'CS담당',
            'Support_Date' => now()->format('Y-m-d'),
            'Meet_Time' => '14:00:00',
            'Support_Type' => '기관이슈',
            'Issue' => '긴급만보기에슈',
            'is_urgent' => true,
            'record_kind' => SupportRecord::KIND_ISSUE,
            'CreatedDate' => now(),
        ]);

        SupportRecord::query()->create([
            'Year' => (int) now()->format('Y'),
            'SK_Code' => 'SK-URG-ISSUE',
            'Account_Name' => '긴급이슈기관',
            'TR_Name' => 'CS담당',
            'Support_Date' => now()->format('Y-m-d'),
            'Meet_Time' => '15:00:00',
            'Support_Type' => '기관이슈',
            'Issue' => '비긴급이슈숨김',
            'is_urgent' => false,
            'record_kind' => SupportRecord::KIND_ISSUE,
            'CreatedDate' => now(),
        ]);

        $user = User::factory()->create(['team' => 'CS']);

        Livewire::actingAs($user)
            ->test(SupportList::class)
            ->assertDontSee('긴급만보기에슈')
            ->assertDontSee('비긴급이슈숨김')
            ->set('filterUrgentOnly', true)
            ->assertSee('긴급만보기에슈')
            ->assertDontSee('비긴급이슈숨김');
    }

    public function test_issue_list_shows_teacher_or_institution_common_label(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-L1',
            'AccountName' => '목록기관',
        ]);

        SupportRecord::query()->create([
            'Year' => (int) now()->format('Y'),
            'SK_Code' => 'SK-L1',
            'Account_Name' => '목록기관',
            'TR_Name' => 'CS담당',
            'Support_Date' => now()->format('Y-m-d'),
            'Meet_Time' => '10:00:00',
            'Support_Type' => '기관이슈',
            'Target' => '이교사',
            'Issue' => '교사 이슈',
            'record_kind' => SupportRecord::KIND_ISSUE,
            'CreatedDate' => now(),
        ]);

        SupportRecord::query()->create([
            'Year' => (int) now()->format('Y'),
            'SK_Code' => 'SK-L1',
            'Account_Name' => '목록기관',
            'TR_Name' => 'CS담당',
            'Support_Date' => now()->format('Y-m-d'),
            'Meet_Time' => '11:00:00',
            'Support_Type' => '기관이슈',
            'Target' => null,
            'Issue' => '공통 이슈',
            'record_kind' => SupportRecord::KIND_ISSUE,
            'CreatedDate' => now(),
        ]);

        $user = User::factory()->create(['team' => 'CS']);

        Livewire::actingAs($user)
            ->test(InstitutionIssueList::class)
            ->assertSee('이교사')
            ->assertSee('기관 공통')
            ->assertSee('교사 이슈')
            ->assertSee('공통 이슈');
    }

    public function test_issue_list_collapses_same_teacher_to_one_row_and_opens_group_modal(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-RS1',
            'AccountName' => '로우스팬기관',
        ]);

        foreach (['이슈A', '이슈B', '이슈C'] as $index => $issue) {
            SupportRecord::query()->create([
                'Year' => (int) now()->format('Y'),
                'SK_Code' => 'SK-RS1',
                'Account_Name' => '로우스팬기관',
                'TR_Name' => 'CS담당',
                'Support_Date' => now()->format('Y-m-d'),
                'Meet_Time' => sprintf('%02d:00:00', 10 + $index),
                'Support_Type' => '기관이슈',
                'Target' => '동일교사',
                'Issue' => $issue,
                'record_kind' => SupportRecord::KIND_ISSUE,
                'CreatedDate' => now()->subMinutes($index),
            ]);
        }

        $user = User::factory()->create(['team' => 'CS']);
        $groupKey = 'sk:SK-RS1|t:동일교사';

        $component = Livewire::actingAs($user)
            ->test(InstitutionIssueList::class)
            ->assertSee('로우스팬기관')
            ->assertSee('동일교사')
            ->assertSee('3건')
            ->assertSee('이슈C')
            ->assertDontSeeHtml('>이슈A</td>')
            ->call('openGroupDetail', $groupKey)
            ->assertSet('showDetailModal', true)
            ->assertSet('selectedGroup.issue_count', 3);

        $latestId = (int) $component->get('selectedGroup.issues.0.id');
        $oldestId = (int) $component->get('selectedGroup.issues.2.id');

        $component
            ->assertSet('expandedIssueId', $latestId)
            ->assertSee('이슈C')
            ->call('toggleExpandedIssue', $oldestId)
            ->assertSet('expandedIssueId', $oldestId)
            ->assertSee('이슈A')
            ->call('closeDetailModal')
            ->assertSet('showDetailModal', false)
            ->assertSet('selectedGroup', null)
            ->assertSet('expandedIssueId', null);
    }

    public function test_issue_list_opens_detail_modal_on_row_click(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-MODAL-1',
            'AccountName' => '모달기관',
        ]);

        SupportRecord::query()->create([
            'Year' => (int) now()->format('Y'),
            'SK_Code' => 'SK-MODAL-1',
            'Account_Name' => '모달기관',
            'TR_Name' => 'CS담당',
            'Support_Date' => now()->format('Y-m-d'),
            'Meet_Time' => '10:30:00',
            'Support_Type' => '기관이슈',
            'Target' => '모달교사',
            'Issue' => "상세 확인용 이슈\n두 번째 줄",
            'is_urgent' => true,
            'record_kind' => SupportRecord::KIND_ISSUE,
            'CreatedDate' => now(),
        ]);

        $user = User::factory()->create(['team' => 'CS']);

        Livewire::actingAs($user)
            ->test(InstitutionIssueList::class)
            ->call('openGroupDetail', 'sk:SK-MODAL-1|t:모달교사')
            ->assertSet('showDetailModal', true)
            ->assertSet('selectedGroup.teacher_label', '모달교사')
            ->assertSet('selectedGroup.issues.0.issue', "상세 확인용 이슈\n두 번째 줄")
            ->assertSet('selectedGroup.issues.0.is_urgent', true)
            ->assertSee('기관 이슈 상세')
            ->assertSee('상세 확인용 이슈')
            ->call('closeDetailModal')
            ->assertSet('showDetailModal', false)
            ->assertSet('selectedGroup', null);
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

    public function test_cs_sidebar_shows_issue_menu(): void
    {
        $cs = User::factory()->create(['team' => 'CS']);

        $this->actingAs($cs)
            ->get('/institutions')
            ->assertOk()
            ->assertSee('기관 이슈')
            ->assertDontSee('기관 이슈 현황')
            ->assertSee('/institution-issues', false);
    }

    public function test_cs_sidebar_stays_open_on_institution_issues_page(): void
    {
        $cs = User::factory()->create(['team' => 'CS']);

        $this->actingAs($cs)
            ->get('/institution-issues?team_menu=cs')
            ->assertOk()
            ->assertSee('openCS: true', false)
            ->assertSee('sidebar-subitem-active', false)
            ->assertSee('기관 이슈', false);
    }
}
