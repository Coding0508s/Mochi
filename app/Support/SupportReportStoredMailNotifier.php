<?php

namespace App\Support;

use App\Mail\SupportReportStoredMail;
use App\Models\SupportRecord;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class SupportReportStoredMailNotifier
{
    /**
     * @return list<string>
     */
    public static function coachTeacherSupportTypeLabels(): array
    {
        static $labels = null;

        if (is_array($labels)) {
            return $labels;
        }

        $configKeys = [
            'coach_teacher_visit',
            'coach_teacher_demo_lesson',
            'coach_teacher_lva_fr',
            'coach_teacher_lva_fb',
            'coach_teacher_ls_onsite_lva',
            'coach_teacher_littleseed_con',
            'coach_teacher_onsite',
            'coach_teacher_pro_con',
            'coach_teacher_open_class',
            'coach_teacher_unit21_plus',
            'coach_teacher_unit31_plus',
        ];

        $labels = [];
        foreach ($configKeys as $key) {
            $label = config("{$key}.support_type_label");
            if (is_string($label) && $label !== '') {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    public static function isCoachTeacherSupportType(?string $supportType): bool
    {
        if (blank($supportType)) {
            return false;
        }

        return in_array($supportType, self::coachTeacherSupportTypeLabels(), true);
    }

    public static function notifyWhenMarkedComplete(
        SupportRecord $supportRecord,
        ?User $submittedBy = null,
        bool $wasCompleted = false,
    ): void {
        if ($wasCompleted || ! $supportRecord->isCompleted()) {
            return;
        }

        if (! self::isCoachTeacherSupportType($supportRecord->Support_Type)) {
            return;
        }

        self::send(
            $supportRecord,
            $submittedBy,
            TeamMenuContext::MENU_COACH,
            'teacher',
        );
    }

    public static function send(
        SupportRecord $supportRecord,
        ?User $submittedBy = null,
        ?string $teamMenu = null,
        string $reportMode = 'institution',
    ): void {
        $notify = config('support_report_mail.notify_addresses', []);
        if ($notify === []) {
            return;
        }

        $resolvedTeamMenu = $teamMenu ?? TeamMenuContext::activeMenu($submittedBy);
        if ($reportMode === 'teacher' && $resolvedTeamMenu === null) {
            $resolvedTeamMenu = TeamMenuContext::MENU_COACH;
        }

        try {
            Mail::to($notify)->send(new SupportReportStoredMail(
                $supportRecord,
                $submittedBy,
                $resolvedTeamMenu,
                $reportMode,
            ));
        } catch (\Throwable $mailException) {
            report($mailException);
            Log::warning('기관 지원 보고서 알림 메일 발송 실패', [
                'exception' => $mailException->getMessage(),
                'notify' => $notify,
                'support_record_id' => $supportRecord->ID,
            ]);

            if (! app()->runningInConsole()) {
                session()->flash(
                    'warning',
                    '지원 보고서는 저장되었지만, 알림 메일 발송에 실패했습니다. Gmail은 앱 비밀번호가 필요할 수 있습니다. `storage/logs/laravel.log`과 `.env`의 MAIL_* 설정을 확인해 주세요.',
                );
            }
        }
    }
}
