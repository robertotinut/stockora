<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        #[\SensitiveParameter]
        private string $token
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the customer-facing single-use password reset URL.
     */
    public function resetUrl(object $notifiable): string
    {
        return url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expireMinutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject(__('notifications.password_reset.subject'))
            ->greeting(__('notifications.password_reset.greeting', ['name' => $notifiable->name ?? '']))
            ->line(__('notifications.password_reset.line1', [
                'email' => $notifiable->getEmailForPasswordReset(),
            ]))
            ->action(__('notifications.password_reset.action'), $this->resetUrl($notifiable))
            ->line(__('notifications.password_reset.expires', ['minutes' => $expireMinutes]))
            ->line(__('notifications.password_reset.ignore'));
    }
}
