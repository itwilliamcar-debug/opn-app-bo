<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ConnectMessage extends Model
{
    use SoftDeletes;

    protected $table = 'connect_messages';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'type',
        'body',
        'emoji_code',
        'audio_path',
        'audio_duration_sec',
        'meta',
    ];

    protected $appends = [
        'audio_url',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'audio_duration_sec' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ConnectConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Public URL for files under storage/app/public/connect/audio.
     * Requires: php artisan storage:link
     */
    public function getAudioUrlAttribute(): ?string
    {
        if (empty($this->audio_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->audio_path);
    }
}
