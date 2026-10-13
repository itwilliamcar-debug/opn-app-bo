<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ConnectConversation extends Model
{
    protected $table = 'connect_conversations';

    protected $fillable = [
        'uuid',
        'type',
        'title',
        'description',
        'jitsi_room',
        'created_by',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ConnectConversation $conversation): void {
            if (empty($conversation->uuid)) {
                $conversation->uuid = (string) Str::uuid();
            }

            if (empty($conversation->jitsi_room)) {
                $conversation->jitsi_room = 'opn-connect-'.Str::lower(Str::random(24));
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Membership pivot rows (role, last_read_at, …).
     */
    public function members(): HasMany
    {
        return $this->hasMany(ConnectConversationMember::class, 'conversation_id');
    }

    /**
     * Alias used by some channel/query helpers.
     */
    public function memberships(): HasMany
    {
        return $this->members();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'connect_conversation_members', 'conversation_id', 'user_id')
            ->withPivot(['role', 'joined_at', 'last_read_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConnectMessage::class, 'conversation_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ConnectMessage::class, 'conversation_id')->latestOfMany();
    }

    /**
     * Only conversations where the given user is a member.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->whereHas('members', function (Builder $memberQuery) use ($userId): void {
            $memberQuery->where('user_id', $userId);
        });
    }

    public function isMember(int|User $user): bool
    {
        $userId = $user instanceof User ? (int) $user->id : (int) $user;

        return $this->members()->where('user_id', $userId)->exists();
    }

    public function memberRole(int|User $user): ?string
    {
        $userId = $user instanceof User ? (int) $user->id : (int) $user;

        return $this->members()->where('user_id', $userId)->value('role');
    }

    public function isOwnerOrAdmin(int|User $user): bool
    {
        return in_array($this->memberRole($user), ['owner', 'admin'], true);
    }

    /**
     * Non-members must never see conversation data (HTTP 403).
     */
    public function ensureMemberOrFail(int|User $user): void
    {
        if (! $this->isMember($user)) {
            abort(403, 'Accès refusé — vous n\'êtes pas membre de cette conversation.');
        }
    }

    public function ensureAdminOrFail(int|User $user): void
    {
        $this->ensureMemberOrFail($user);

        if (! $this->isOwnerOrAdmin($user)) {
            abort(403, 'Action réservée au propriétaire ou administrateur de la conversation.');
        }
    }
}
