<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use App\Support\CoachTeacherScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeputyAdminScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_deputy_admin_skips_tr_scope_while_coach_user_is_scoped(): void
    {
        $deputy = User::factory()->deputyAdmin()->create([
            'team' => 'COACH',
        ]);

        $deputyQuery = Teacher::query();
        CoachTeacherScope::apply($deputyQuery, $deputy);

        $coach = User::factory()->create([
            'team' => 'COACH',
            'email' => 'scoped-coach@example.com',
            'name' => 'Scoped Coach',
        ]);

        $coachQuery = Teacher::query();
        CoachTeacherScope::apply($coachQuery, $coach);

        $deputySql = strtolower($deputyQuery->toSql());
        $coachSql = strtolower($coachQuery->toSql());

        $this->assertStringNotContainsString('exists', $deputySql);
        $this->assertStringContainsString('exists', $coachSql);
    }
}
