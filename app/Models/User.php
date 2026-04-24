<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

#[Fillable(['name', 'email', 'employee_empno', 'password', 'is_admin', 'team', 'is_gs_brochure_admin', 'can_manage_store_inventory', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_empno', 'EMPNO');
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function isCoTeam(): bool
    {
        return $this->team === 'CO';
    }

    public function hasFullAccess(): bool
    {
        return (bool) $this->is_admin;
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
            'is_admin' => 'boolean',
            'is_gs_brochure_admin' => 'boolean',
            'can_manage_store_inventory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
