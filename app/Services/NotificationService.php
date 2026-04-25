<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\User;

class NotificationService
{
    /**
     * Send an in-app notification to a user.
     * Email binding is stubbed via logger — wire up Mail facade later.
     */
    public static function notify(int $recipientUserId, string $type, string $title, ?string $body = null, ?string $linkUrl = null, array $context = [], bool $alsoEmail = false): NotificationLog
    {
        $log = NotificationLog::create([
            'recipient_user_id' => $recipientUserId,
            'type'              => $type,
            'title'             => $title,
            'body'              => $body,
            'link_url'          => $linkUrl,
            'context'           => $context,
            'channel'           => $alsoEmail ? 'email' : 'in_app',
            'sent_at'           => now(),
        ]);

        if ($alsoEmail) {
            // TODO(email): bind real mailer once SMTP is configured.
            $user = User::find($recipientUserId);
            if ($user && $user->email) {
                logger()->info('[email-stub] '.$title, [
                    'to' => $user->email, 'body' => $body, 'link' => $linkUrl,
                ]);
            }
        }

        return $log;
    }

    public static function notifyAdmins(string $type, string $title, ?string $body = null, ?string $linkUrl = null, array $context = []): void
    {
        $admins = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['admin']);
        })->pluck('id');

        foreach ($admins as $adminId) {
            self::notify($adminId, $type, $title, $body, $linkUrl, $context);
        }
    }

    public static function unreadCountFor(int $userId): int
    {
        return NotificationLog::where('recipient_user_id', $userId)->whereNull('read_at')->count();
    }

    public static function recentFor(int $userId, int $limit = 15)
    {
        return NotificationLog::where('recipient_user_id', $userId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
