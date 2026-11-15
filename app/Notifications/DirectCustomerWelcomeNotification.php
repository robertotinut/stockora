<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DirectCustomerWelcomeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $companyName,
        private string $companyCode,
        private string $setupUrl
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.welcome.subject', ['company' => $this->companyName]))
            ->greeting(__('notifications.welcome.greeting', ['name' => $notifiable->name ?? '']))
            ->line(__('notifications.welcome.line1', [
                'company' => $this->companyName,
                'code' => $this->companyCode,
            ]))
            ->action(__('notifications.welcome.action'), $this->setupUrl)
            ->line(__('notifications.welcome.expires'))
            ->line(__('notifications.welcome.ignore'));
    }
}
