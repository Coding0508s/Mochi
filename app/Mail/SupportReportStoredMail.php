<?php

namespace App\Mail;

use App\Models\SupportRecord;
use App\Models\User;
use App\Support\TeamMenuContext;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportReportStoredMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $supportDate;

    public string $meetTime;

    public string $submitterLabel;

    /** 예: "Coach Team 기관 지원 보고서" */
    public string $reportSavedOpening;

    /** 예: "Coach" */
    public string $reportAssigneeColumnLabel;

    public function __construct(
        public SupportRecord $supportRecord,
        public ?User $submittedBy = null,
        public ?string $teamMenu = null,
        public string $reportMode = 'institution',
    ) {
        $sd = $supportRecord->Support_Date;
        $this->supportDate = $sd instanceof DateTimeInterface
            ? $sd->format('Y-m-d')
            : (filled($sd) ? (string) $sd : '—');

        $mt = $supportRecord->Meet_Time;
        $this->meetTime = $mt instanceof DateTimeInterface
            ? $mt->format('H:i')
            : (filled($mt) ? (string) $mt : '—');

        if (! $submittedBy instanceof User) {
            $this->submitterLabel = '—';
        } else {
            $name = trim((string) ($submittedBy->name ?? ''));
            $email = trim((string) ($submittedBy->email ?? ''));
            $this->submitterLabel = match (true) {
                $name !== '' && $email !== '' => $name.' <'.$email.'>',
                $email !== '' => $email,
                $name !== '' => $name,
                default => '—',
            };
        }

        $this->reportSavedOpening = $reportMode === 'teacher'
            ? TeamMenuContext::teacherSupportReportMailOpening($submittedBy, $this->teamMenu)
            : TeamMenuContext::institutionSupportReportMailOpening($submittedBy, $this->teamMenu);
        $this->reportAssigneeColumnLabel = $reportMode === 'teacher'
            ? TeamMenuContext::institutionSupportReportAssigneeLabel($submittedBy, $this->teamMenu)
            : TeamMenuContext::institutionSupportReportMailAssigneeColumnLabel($submittedBy, $this->teamMenu);
    }

    public function envelope(): Envelope
    {
        $label = filled($this->supportRecord->Account_Name)
            ? (string) $this->supportRecord->Account_Name
            : ($this->reportMode === 'teacher' ? '교사' : '기관');

        $prefix = $this->reportMode === 'teacher'
            ? TeamMenuContext::teacherSupportReportMailSubjectPrefix($this->submittedBy, $this->teamMenu)
            : TeamMenuContext::institutionSupportReportMailSubjectPrefix($this->submittedBy, $this->teamMenu);

        return new Envelope(
            subject: $prefix.' '.$label,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.support-report-stored',
            text: 'mail.support-report-stored-text',
        );
    }
}
