<?php

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        return response()->json([
            'unread' => NotificationService::unreadCountFor($userId),
            'items'  => NotificationService::recentFor($userId, 15)->map(fn ($n) => [
                'id'       => $n->id,
                'type'     => $n->type,
                'title'    => $n->title,
                'body'     => $n->body,
                'link_url' => $n->link_url,
                'read_at'  => $n->read_at?->toIso8601String(),
                'sent_at'  => $n->sent_at?->toIso8601String(),
            ]),
        ]);
    }

    public function markRead(Request $request, NotificationLog $notification)
    {
        abort_unless($notification->recipient_user_id === $request->user()->id, 403);
        $notification->markRead();
        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request)
    {
        NotificationLog::where('recipient_user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }
}
