<?php

namespace App\Mail;

use App\Models\CoNewTarget;
use App\Models\CoNewTargetDetail;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PotentialMeetingStoredMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $meetingDate;

    public string $meetingTimeRange;

    public string $submitterLabel;

    public string $meetingSavedOpening;

    /** @var array{ls: int|null, gs_k: int|null, gs_e: int|null, total: int|null} */
    public array $studentCounts;

    public function __construct(
        public CoNewTargetDetail $meetingDetail,
        public ?User $submittedBy = null,
        public ?CoNewTarget $target = null,
    ) {
        $md = $meetingDetail->MeetingDate;
        $this->meetingDate = $md instanceof DateTimeInterface
            ? $md->format('Y-m-d')
            : (filled($md) ? (string) $md : '—');

        $start = filled($meetingDetail->MeetingTime) ? trim((string) $meetingDetail->MeetingTime) : '';
        $end = filled($meetingDetail->MeetingTime_End) ? trim((string) $meetingDetail->MeetingTime_End) : '';
        $this->meetingTimeRange = match (true) {
            $start !== '' && $end !== '' => $start.' ~ '.$end,
            $start !== '' => $start,
            $end !== '' => $end,
            default => '—',
        };

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

        $team = ($submittedBy instanceof User && filled(trim((string) ($submittedBy->team ?? ''))))
            ? trim((string) $submittedBy->team)
            : null;

        $this->meetingSavedOpening = $team !== null
            ? $team.'팀 잠재기관 미팅 내역'
            : '잠재기관 미팅 내역';

        $target ??= CoNewTarget::query()
            ->where('AccountName', $meetingDetail->AccountName)
            ->latest('ID')
            ->first();

        $this->studentCounts = [
            'ls' => $this->nullableInt($target?->LS),
            'gs_k' => $this->nullableInt($target?->GS_K),
            'gs_e' => $this->nullableInt($target?->GS_E),
            'total' => $target !== null ? $target->studentTotal() : null,
        ];
    }

    public function envelope(): Envelope
    {
        $label = filled($this->meetingDetail->AccountName)
            ? (string) $this->meetingDetail->AccountName
            : '잠재기관';

        return new Envelope(
            subject: '[잠재기관 미팅] '.$label,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.potential-meeting-stored',
            text: 'mail.potential-meeting-stored-text',
        );
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
