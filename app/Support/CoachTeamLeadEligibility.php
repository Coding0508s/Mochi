<?php

namespace App\Support;

final class CoachTeamLeadEligibility
{
    public static function recommendsTeamKpi(string $job, string $workDept, ?int $status): bool
    {
        if (! self::isActiveStatus($status)) {
            return false;
        }

        if (! self::isTeamLeadJob($job)) {
            return false;
        }

        return self::isCoachWorkDept($workDept);
    }

    public static function allowsTeamKpiCheckbox(bool $checkboxOn, string $job, string $workDept, ?int $status): bool
    {
        if (! $checkboxOn) {
            return true;
        }

        return self::recommendsTeamKpi($job, $workDept, $status);
    }

    private static function isActiveStatus(?int $status): bool
    {
        return $status === 1;
    }

    private static function isCoachWorkDept(string $workDept): bool
    {
        $allowed = config('coach_team_kpi.coach_work_depts', []);
        if (! is_array($allowed)) {
            return false;
        }

        $normalizedWorkDept = self::normalizeToken($workDept);
        if ($normalizedWorkDept === '') {
            return false;
        }

        foreach ($allowed as $dept) {
            if ($normalizedWorkDept === self::normalizeToken((string) $dept)) {
                return true;
            }
        }

        return false;
    }

    private static function isTeamLeadJob(string $job): bool
    {
        $normalizedJob = self::normalizeJob($job);
        if ($normalizedJob === '') {
            return false;
        }

        $allowedJobs = config('coach_team_kpi.team_lead_jobs', []);
        if (is_array($allowedJobs)) {
            foreach ($allowedJobs as $allowedJob) {
                if ($normalizedJob === self::normalizeJob((string) $allowedJob)) {
                    return true;
                }
            }
        }

        $aliases = config('coach_team_kpi.job_aliases', []);
        if (is_array($aliases)) {
            foreach ($aliases as $alias) {
                if ($normalizedJob === self::normalizeJob((string) $alias)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function normalizeJob(string $job): string
    {
        return mb_strtoupper(str_replace(' ', '', trim($job)));
    }

    private static function normalizeToken(string $value): string
    {
        return mb_strtoupper(trim($value));
    }
}
