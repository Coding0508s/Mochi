<?php

namespace App\Support;

use App\Models\User;

/**
 * FGC_Creator / FGC_LastModifier(이메일) → 처리자 표시명 매핑.
 *
 * 직원 마스터 ENGLISHNAME 우선(User::nameForCoReports), 없을 때만 users.name.
 */
class LegacyAuditUserNames
{
    /**
     * @param  iterable<int, string|null>  $emails
     * @return array<string, string> email(lowercase) => display name
     */
    public static function mapByEmail(iterable $emails): array
    {
        $normalized = collect($emails)
            ->map(static fn (?string $email): string => trim((string) $email))
            ->filter(static fn (string $email): bool => $email !== '')
            ->unique(static fn (string $email): string => strtolower($email))
            ->values();

        if ($normalized->isEmpty()) {
            return [];
        }

        $lowered = $normalized
            ->map(static fn (string $email): string => strtolower($email))
            ->all();

        return User::query()
            ->where(function ($query) use ($lowered): void {
                foreach ($lowered as $email) {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }
            })
            ->get()
            ->mapWithKeys(static function (User $user): array {
                $email = strtolower(trim((string) $user->email));
                $name = trim($user->nameForCoReports());

                return $email !== '' && $name !== ''
                    ? [$email => $name]
                    : [];
            })
            ->all();
    }

    /**
     * @param  array<string, string>  $namesByEmail
     */
    public static function displayName(?string $email, array $namesByEmail): string
    {
        $key = strtolower(trim((string) $email));
        if ($key === '') {
            return '';
        }

        return trim((string) ($namesByEmail[$key] ?? ''));
    }

    /**
     * Creator 우선, 없으면 LastModifier.
     */
    public static function preferredEmail(?string $creator, ?string $lastModifier): string
    {
        $creator = trim((string) $creator);
        if ($creator !== '') {
            return $creator;
        }

        return trim((string) $lastModifier);
    }
}
