<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Notifications\TicketingNotice;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class TicketingNotifier
{
    /**
     * Send an email to an account holder.
     *
     * @param  array<string, string>|null  $action
     */
    public static function user(?User $user, string $subject, string $message, ?array $action = null): void
    {
        if ($user !== null && filled($user->email)) {
            try {
                $user->notify(new TicketingNotice($subject, $message, $action));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * Send an email to the configured platform administrator address.
     *
     * @param  array<string, string>|null  $action
     */
    public static function admin(string $subject, string $message, ?array $action = null): void
    {
        $email = trim((string) config('ticketing.admin.email', ''));

        if ($email !== '') {
            try {
                Notification::route('mail', $email)->notify(new TicketingNotice($subject, $message, $action));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
