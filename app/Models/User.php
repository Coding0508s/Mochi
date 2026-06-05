<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\CustomResetPassword;
use App\Support\TeamMenuContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

#[Fillable(['name', 'email', 'employee_empno', 'password', 'must_change_password', 'is_admin', 'team', 'is_gs_brochure_admin', 'can_manage_store_inventory', 'is_coach_team_lead', 'is_deputy_admin', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_empno', 'EMPNO');
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token)
    {
        $this->notify(new CustomResetPassword((string) $token));
    }

    public function isCoTeam(): bool
    {
        return $this->resolvedTeamCode() === 'CO';
    }

    public function isCsTeam(): bool
    {
        return $this->resolvedTeamCode() === 'CS';
    }

    public function isCoachTeam(): bool
    {
        return in_array($this->resolvedTeamCode(), ['COACH', 'TR', 'TRAINING'], true);
    }

    public function resolvedTeamCode(): string
    {
        return TeamMenuContext::resolveTeamCode($this);
    }

    public function hasFullAccess(): bool
    {
        return (bool) $this->is_admin;
    }

    public function isDeputyAdmin(): bool
    {
        return ! $this->hasFullAccess() && (bool) $this->is_deputy_admin;
    }

    /** 팀·담당자 스코프 없이 플랫폼 데이터 전체 조회 */
    public function hasPlatformWideViewAccess(): bool
    {
        return $this->hasFullAccess() || (bool) $this->is_deputy_admin;
    }

    /** 삭제 등 파괴적 작업 — Full Access(관리자)만 */
    public function canDeletePlatformData(): bool
    {
        return $this->hasFullAccess();
    }

    public function canViewCoachTeamKpi(): bool
    {
        return $this->hasPlatformWideViewAccess() || (bool) $this->is_coach_team_lead;
    }

    /**
     * 기관지원보고서 CO명 등: 직원 마스터 **영문명(ENGLISHNAME)** 우선, 없을 때만 계정/한글명.
     */
    public function nameForCoReports(): string
    {
        if (Schema::hasTable('employee')) {
            if (filled($this->employee_empno)) {
                $byEmpNo = Employee::query()->where('EMPNO', $this->employee_empno)->value('ENGLISHNAME');
                if (is_string($byEmpNo) && trim($byEmpNo) !== '') {
                    return trim($byEmpNo);
                }
            }

            $email = mb_strtolower(trim((string) $this->email));
            if ($email !== '') {
                $byEmail = Employee::query()
                    ->whereRaw('LOWER(TRIM(COALESCE(EMAIL, \'\'))) = ?', [$email])
                    ->value('ENGLISHNAME');
                if (is_string($byEmail) && trim($byEmail) !== '') {
                    return trim($byEmail);
                }
            }
        }

        $fromUser = trim((string) $this->name);
        if ($fromUser !== '') {
            return $fromUser;
        }

        if (filled($this->employee_empno) && Schema::hasTable('employee')) {
            $korean = Employee::query()->where('EMPNO', $this->employee_empno)->value('KOREANAME');
            if (is_string($korean) && trim($korean) !== '') {
                return trim($korean);
            }
        }

        return $this->preferredDisplayName();
    }

    public function preferredDisplayName(): string
    {
        $email = mb_strtolower(trim((string) $this->email));
        if ($email !== '' && Schema::hasTable('employee')) {
            $englishName = Employee::query()
                ->whereRaw('LOWER(TRIM(COALESCE(EMAIL, \'\'))) = ?', [$email])
                ->value('ENGLISHNAME');

            if (is_string($englishName) && trim($englishName) !== '') {
                return trim($englishName);
            }
        }

        $name = trim((string) $this->name);
        if ($name !== '') {
            return $name;
        }

        if ($email !== '') {
            return trim((string) $this->email);
        }

        return 'User';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'is_admin' => 'boolean',
            'is_gs_brochure_admin' => 'boolean',
            'can_manage_store_inventory' => 'boolean',
            'is_coach_team_lead' => 'boolean',
            'is_deputy_admin' => 'boolean',
            'is_active' => 'boolean',
            'last_inbound_seen_at' => 'datetime',
        ];
    }

    private function normalizedTeamCode(): string
    {
        return $this->resolvedTeamCode();
    }
}
