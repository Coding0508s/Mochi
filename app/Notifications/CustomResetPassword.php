<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomResetPassword extends Notification
{
    public function __construct(
        #[\SensitiveParameter]
        public string $token
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);
        $broker = (string) config('auth.defaults.passwords');
        $expireMinutes = (int) config("auth.passwords.{$broker}.expire", 60);

        $recipientName = $notifiable instanceof User
            ? $notifiable->preferredDisplayName()
            : trim((string) ($notifiable->name ?? ''));

        $data = [
            'resetUrl' => $url,
            'expireMinutes' => $expireMinutes,
            'appName' => (string) config('app.name'),
            'recipientName' => $recipientName !== '' ? $recipientName : '고객',
            'actionLabel' => (string) config('password_reset_mail.action_label'),
        ];

        return (new MailMessage)
            ->subject((string) config('password_reset_mail.subject'))
            ->view('mail.password-reset', $data)
            ->text('mail.password-reset-text', $data);
    }

    protected function resetUrl(object $notifiable): string
    {
        return url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}
