<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SetTemporaryUserPassword
{
    /**
     * @return string Plain temporary password (shown once to the admin).
     */
    public function execute(User $target, User $admin, bool $privilegedTargetConfirmed = false): string
    {
        Gate::forUser($admin)->authorize('manageUserAccounts');

        if ($target->id === $admin->id) {
            throw ValidationException::withMessages([
                'tempPassword' => ['본인 계정에는 임시 비밀번호를 발급할 수 없습니다.'],
            ]);
        }

        if (! (bool) $target->is_active) {
            throw ValidationException::withMessages([
                'tempPassword' => ['비활성 계정에는 임시 비밀번호를 발급할 수 없습니다.'],
            ]);
        }

        if ($this->targetRequiresPrivilegedConfirmation($target) && ! $privilegedTargetConfirmed) {
            throw ValidationException::withMessages([
                'tempPasswordPrivilegedConfirm' => ['관리자 권한 계정입니다. 확인 후 진행해 주세요.'],
            ]);
        }

        $plainPassword = Str::password(12, symbols: false);

        $target->forceFill([
            'password' => $plainPassword,
            'must_change_password' => true,
            'remember_token' => null,
        ])->save();

        Log::info('[admin] temporary password set', [
            'admin_id' => $admin->id,
            'target_user_id' => $target->id,
            'target_empno' => $target->employee_empno,
        ]);

        return $plainPassword;
    }

    public function targetRequiresPrivilegedConfirmation(User $target): bool
    {
        return (bool) $target->is_admin || (bool) $target->is_deputy_admin;
    }
}
