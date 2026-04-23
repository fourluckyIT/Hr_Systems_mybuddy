<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RecordingSession extends Model
{
    protected $fillable = [
        'session_date',
        'title',
        'game_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function youtubers(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'recording_session_youtuber')
            ->withTimestamps();
    }
}
