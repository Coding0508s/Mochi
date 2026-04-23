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

#[Fillable(['name', 'email', 'employee_empno', 'password', 'is_admin', 'team', 'is_gs_brochure_admin', 'is_active'])]
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

    public function isCountryManager(): bool
    {
        $email = mb_strtolower(trim((string) $this->email));
        if ($email === '' || ! Schema::hasTable('employee')) {
            return false;
        }

        $job = Employee::query()
            ->whereRaw('LOWER(TRIM(COALESCE(EMAIL, \'\'))) = ?', [$email])
            ->value('JOB');

        if (! is_string($job) || trim($job) === '') {
            return false;
        }

        $normalizedJob = mb_strtolower(trim($job));

        return in_array($normalizedJob, [
            'country manager',
            'countrymanager',
        ], true);
    }

    public function hasFullAccess(): bool
    {
        return (bool) $this->is_admin || $this->isCountryManager();
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
            'is_active' => 'boolean',
        ];
    }
}
