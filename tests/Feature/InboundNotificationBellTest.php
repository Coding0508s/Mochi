<?php

namespace Tests\Feature;

use App\Livewire\InboundNotificationBell;
use App\Models\ExternalAssignmentInboundLog;
use App\Models\UrgentSupportNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InboundNotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_sees_unread_inbound_notification_badge(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        ExternalAssignmentInboundLog::query()->create([
            'sk_code' => 'SK1234',
            'co' => 'Peter.Kim',
            'tr' => 'Rami.Lee',
            'cs' => 'Bella.Joo',
            'raw_body' => [
                'source' => 'sk_code_request',
                'institution_name' => 'TEA',
            ],
            'status' => 'applied',
            'received_at' => now(),
            'applied_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(InboundNotificationBell::class)
            ->assertSet('unreadCount', 1)
            ->assertSee('1')
            ->call('loadPanelData')
            ->assertSee('TEA 기관 정보가 반영되었습니다.');
    }

    public function test_regular_user_can_mark_inbound_notifications_as_read(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->createInboundLog('SK-READ-1', '읽음 테스트 기관');

        Livewire::actingAs($user)
            ->test(InboundNotificationBell::class)
            ->assertSet('unreadCount', 1)
            ->call('markAllAsRead')
            ->assertSet('unreadCount', 0);

        $this->assertNotNull($user->fresh()->last_inbound_seen_at);
    }

    public function test_regular_user_can_dismiss_inbound_notifications_individually(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $first = $this->createInboundLog('SK-DELETE-1', '개별 삭제 기관');
        $this->createInboundLog('SK-DELETE-2', '남는 기관');

        Livewire::actingAs($user)
            ->test(InboundNotificationBell::class)
            ->call('deleteLog', $first->id)
            ->assertSet('unreadCount', 1)
            ->assertDontSee('개별 삭제 기관')
            ->assertSee('남는 기관');

        // 실제 로그는 보존되고 사용자별 dismiss 테이블에만 흔적이 남아야 합니다.
        $this->assertDatabaseHas('external_assignment_inbound_logs', [
            'id' => $first->id,
        ]);
        $this->assertDatabaseHas('inbound_notification_dismissals', [
            'user_id' => $user->id,
            'log_id' => $first->id,
        ]);
    }

    public function test_regular_user_dismiss_all_only_hides_own_view(): void
    {
        $alice = User::factory()->create(['is_admin' => false]);
        $bob = User::factory()->create(['is_admin' => false]);

        $this->createInboundLog('SK-ALL-1', '공동 알림 1');
        $this->createInboundLog('SK-ALL-2', '공동 알림 2');

        Livewire::actingAs($alice)
            ->test(InboundNotificationBell::class)
            ->call('deleteAllLogs')
            ->assertSet('unreadCount', 0)
            ->assertDontSee('공동 알림 1')
            ->assertDontSee('공동 알림 2');

        // 다른 사용자(Bob)는 동일한 알림을 그대로 봐야 합니다.
        Livewire::actingAs($bob)
            ->test(InboundNotificationBell::class)
            ->assertSet('unreadCount', 2)
            ->call('loadPanelData')
            ->assertSee('공동 알림 1')
            ->assertSee('공동 알림 2');

        $this->assertDatabaseCount('external_assignment_inbound_logs', 2);
    }

    public function test_regular_user_sees_unread_urgent_notification_badge(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);
        $sender = User::factory()->create([
            'is_admin' => true,
        ]);

        UrgentSupportNotification::query()->create([
            'support_record_id' => 100,
            'recipient_user_id' => $user->id,
            'sender_user_id' => $sender->id,
            'sk_code' => 'SK-U-1',
            'account_name' => '긴급 기관',
            'message' => '긴급 안내 본문',
            'is_read' => false,
        ]);

        Livewire::actingAs($user)
            ->test(InboundNotificationBell::class)
            ->assertSet('unreadCount', 1)
            ->call('loadPanelData')
            ->assertSee('[긴급] 긴급 기관 기관 지원 보고서')
            ->assertSee('긴급 안내 본문');
    }

    public function test_mark_all_as_read_marks_urgent_notifications_as_read(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $sender = User::factory()->create(['is_admin' => true]);

        $urgent = UrgentSupportNotification::query()->create([
            'support_record_id' => 200,
            'recipient_user_id' => $user->id,
            'sender_user_id' => $sender->id,
            'sk_code' => 'SK-U-2',
            'account_name' => '긴급 읽음 기관',
            'message' => '읽음 처리 확인',
            'is_read' => false,
        ]);

        Livewire::actingAs($user)
            ->test(InboundNotificationBell::class)
            ->assertSet('unreadCount', 1)
            ->call('markAllAsRead')
            ->assertSet('unreadCount', 0)
            ->assertSee('[긴급] 긴급 읽음 기관 기관 지원 보고서');

        $this->assertDatabaseHas('urgent_support_notifications', [
            'id' => $urgent->id,
            'is_read' => true,
        ]);
        $this->assertNull($urgent->fresh()->dismissed_at);
    }

    public function test_regular_user_can_dismiss_urgent_notifications_individually(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $sender = User::factory()->create(['is_admin' => true]);

        $first = UrgentSupportNotification::query()->create([
            'support_record_id' => 201,
            'recipient_user_id' => $user->id,
            'sender_user_id' => $sender->id,
            'sk_code' => 'SK-U-D1',
            'account_name' => '긴급 삭제 기관',
            'message' => '삭제 대상',
            'is_read' => false,
        ]);

        UrgentSupportNotification::query()->create([
            'support_record_id' => 202,
            'recipient_user_id' => $user->id,
            'sender_user_id' => $sender->id,
            'sk_code' => 'SK-U-D2',
            'account_name' => '남는 긴급 기관',
            'message' => '남는 알림',
            'is_read' => false,
        ]);

        Livewire::actingAs($user)
            ->test(InboundNotificationBell::class)
            ->call('deleteLog', 'urgent:'.$first->id)
            ->assertSet('unreadCount', 1)
            ->assertDontSee('[긴급] 긴급 삭제 기관 기관 지원 보고서')
            ->assertSee('[긴급] 남는 긴급 기관 기관 지원 보고서');

        $this->assertDatabaseHas('urgent_support_notifications', [
            'id' => $first->id,
            'is_read' => true,
        ]);
        $this->assertNotNull($first->fresh()->dismissed_at);
    }

    public function test_regular_user_dismiss_all_hides_urgent_notifications(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $sender = User::factory()->create(['is_admin' => true]);

        UrgentSupportNotification::query()->create([
            'support_record_id' => 203,
            'recipient_user_id' => $user->id,
            'sender_user_id' => $sender->id,
            'sk_code' => 'SK-U-A1',
            'account_name' => '긴급 전체 삭제 1',
            'message' => '전체 삭제 1',
            'is_read' => false,
        ]);

        UrgentSupportNotification::query()->create([
            'support_record_id' => 204,
            'recipient_user_id' => $user->id,
            'sender_user_id' => $sender->id,
            'sk_code' => 'SK-U-A2',
            'account_name' => '긴급 전체 삭제 2',
            'message' => '전체 삭제 2',
            'is_read' => false,
        ]);

        Livewire::actingAs($user)
            ->test(InboundNotificationBell::class)
            ->call('deleteAllLogs')
            ->assertSet('unreadCount', 0)
            ->assertDontSee('[긴급] 긴급 전체 삭제 1 기관 지원 보고서')
            ->assertDontSee('[긴급] 긴급 전체 삭제 2 기관 지원 보고서');
    }

    public function test_notifications_updated_event_refreshes_bell_counters(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $sender = User::factory()->create(['is_admin' => true]);

        $component = Livewire::actingAs($user)
            ->test(InboundNotificationBell::class)
            ->assertSet('unreadCount', 0);

        UrgentSupportNotification::query()->create([
            'support_record_id' => 300,
            'recipient_user_id' => $user->id,
            'sender_user_id' => $sender->id,
            'sk_code' => 'SK-U-3',
            'account_name' => '이벤트 갱신 기관',
            'message' => '갱신 확인',
            'is_read' => false,
        ]);

        $component
            ->dispatch('notifications-updated')
            ->assertSet('unreadCount', 1)
            ->call('loadPanelData')
            ->assertSee('[긴급] 이벤트 갱신 기관 기관 지원 보고서');
    }

    private function createInboundLog(string $skCode, string $institutionName): ExternalAssignmentInboundLog
    {
        return ExternalAssignmentInboundLog::query()->create([
            'sk_code' => $skCode,
            'co' => 'Peter.Kim',
            'tr' => 'Rami.Lee',
            'cs' => 'Bella.Joo',
            'raw_body' => [
                'source' => 'sk_code_request',
                'institution_name' => $institutionName,
            ],
            'status' => 'applied',
            'received_at' => now(),
            'applied_at' => now(),
        ]);
    }
}
