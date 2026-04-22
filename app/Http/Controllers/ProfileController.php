<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
        $englishName = '';
        $email = mb_strtolower(trim((string) ($user?->email ?? '')));
        if ($email !== '' && Schema::hasTable('employee')) {
            $englishName = trim((string) (Employee::query()
                ->whereRaw('LOWER(TRIM(COALESCE(EMAIL, \'\'))) = ?', [$email])
                ->value('ENGLISHNAME') ?? ''));
        }

        return view('profile.edit', [
            'user' => $user,
            'englishName' => $englishName,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $englishName = trim((string) ($validated['english_name'] ?? ''));
        $normalizedCurrentEmail = mb_strtolower(trim((string) ($user?->email ?? '')));
        $normalizedSubmittedEmail = mb_strtolower(trim((string) $request->input('email', '')));

        if ($normalizedSubmittedEmail === '' || $normalizedSubmittedEmail !== $normalizedCurrentEmail) {
            throw ValidationException::withMessages([
                'email' => '이메일은 프로필에서 변경할 수 없습니다. 변경이 필요하면 관리자에게 요청해 주세요.',
            ]);
        }

        if (! Schema::hasTable('employee')) {
            throw ValidationException::withMessages([
                'english_name' => '직원 정보 테이블이 없어 영어 이름을 동기화할 수 없습니다.',
            ]);
        }

        DB::transaction(function () use ($user, $validated, $englishName, $normalizedCurrentEmail): void {
            $employee = Employee::query()
                ->whereRaw('LOWER(TRIM(COALESCE(EMAIL, \'\'))) = ?', [$normalizedCurrentEmail])
                ->first();

            if (! $employee) {
                throw ValidationException::withMessages([
                    'english_name' => '직원 마스터에서 현재 이메일과 일치하는 계정을 찾지 못했습니다. 영어 이름을 저장하려면 관리자에게 문의해 주세요.',
                ]);
            }

            $user->fill([
                'name' => $validated['name'],
            ]);

            $user->save();
            $employee->update([
                'ENGLISHNAME' => $englishName,
            ]);
        });

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        abort_if(! $request->user()?->hasFullAccess(), 403, '관리자 권한이 필요합니다.');

        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
