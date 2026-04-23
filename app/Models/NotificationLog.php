<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'recipient_user_id', 'type', 'title', 'body',
        'link_url', 'context', 'channel', 'read_at', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'read_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function scopeUnread($q)
    {
        return $q->whereNull('read_at');
    }

    public function markRead(): void
    {
        if (is_null($this->read_at)) {
            $this->update(['read_at' => now()]);
        }
    }
}
