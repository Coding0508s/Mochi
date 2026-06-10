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

class UrgentSupportNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $supportDate;

    public string $meetTime;

    public string $reportListUrl;

    public string $senderName;

    public function __construct(
        public SupportRecord $supportRecord,
        public User $recipient,
        public User $sender,
        public ?string $teamMenu = null,
    ) {
        $sd = $supportRecord->Support_Date;
        $this->supportDate = $sd instanceof DateTimeInterface
            ? $sd->format('Y-m-d')
            : (filled($sd) ? (string) $sd : '—');

        $mt = $supportRecord->Meet_Time;
        $this->meetTime = $mt instanceof DateTimeInterface
            ? $mt->format('H:i')
            : (filled($mt) ? (string) $mt : '—');

        $resolvedTeam = $this->teamMenu ?? TeamMenuContext::activeMenu($sender) ?? 'co';
        $this->reportListUrl = route('supports.index', ['team_menu' => $resolvedTeam]);
        $this->senderName = $sender->preferredDisplayName();
    }

    public function envelope(): Envelope
    {
        $label = filled($this->supportRecord->Account_Name)
            ? (string) $this->supportRecord->Account_Name
            : '기관';

        return new Envelope(
            subject: "[긴급] {$label} 기관 지원 보고서",
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.urgent-support-notification',
            text: 'mail.urgent-support-notification-text',
        );
    }
}
