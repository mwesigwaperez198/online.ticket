<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TicketingNotice extends Notification
{
    use Queueable;

    /**
     * @param  array<string, string>|null  $action
     */
    public function __construct(
        private readonly string $subject,
        private readonly string $message,
        private readonly ?array $action = null,
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
        $mail = (new MailMessage)
            ->subject($this->subject)
            ->greeting('Hello '.($notifiable->name ?? 'there').',')
            ->line($this->message)
            ->line('Keep this email for your records.');

        if ($this->action !== null) {
            $mail->action($this->action['label'], $this->action['url']);
        }

        return $mail;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return ['subject' => $this->subject, 'message' => $this->message];
    }
}
