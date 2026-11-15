<?php

namespace App\Notifications;

use App\Services\PermissionRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $companyName,
        private string $inviterName,
        private string $role,
        private string $acceptUrl,
        private string $expiresAtFormatted,
        private ?string $personalMessage = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $roleKey = 'permissions.roles.' . $this->role . '.label';
        $translatedRole = __($roleKey);
        $roleLabel = $translatedRole !== $roleKey
            ? $translatedRole
            : (PermissionRegistry::ROLES[$this->role]['label'] ?? strtoupper($this->role));

        $mail = (new MailMessage)
            ->subject(__('notifications.invitation.subject', ['company' => $this->companyName]))
            ->greeting(__('notifications.invitation.greeting'))
            ->line(__('notifications.invitation.line1', [
                'inviter' => $this->inviterName,
                'company' => $this->companyName,
                'role' => $roleLabel,
            ]));

        if ($this->personalMessage) {
            $mail->line(__('notifications.invitation.personal_note', ['note' => $this->personalMessage]));
        }

        return $mail
            ->action(__('notifications.invitation.action'), $this->acceptUrl)
            ->line(__('notifications.invitation.expires', ['date' => $this->expiresAtFormatted]))
            ->line(__('notifications.invitation.ignore'));
    }
}
