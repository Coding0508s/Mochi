<?php

namespace Tests\Feature;

use App\Models\AccountAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_persists_audit_entry_without_updated_at(): void
    {
        $target = User::factory()->create();
        $actor = User::factory()->admin()->create();

        AccountAuditLog::record($target, $actor, 'permission_changed', [
            'setup_manage' => ['before' => false, 'after' => true],
        ]);

        $this->assertDatabaseHas('account_audit_logs', [
            'user_id' => $target->id,
            'actor_id' => $actor->id,
            'action' => 'permission_changed',
        ]);

        $log = AccountAuditLog::query()->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->created_at);
        $this->assertTrue($log->changes['setup_manage']['after']);
    }
}
