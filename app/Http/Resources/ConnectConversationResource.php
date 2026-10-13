<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConnectConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'jitsi_room' => $this->jitsi_room,
            'created_by' => $this->created_by,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'members_count' => $this->when(
                isset($this->members_count) || $this->relationLoaded('members'),
                fn () => $this->members_count ?? $this->members->count()
            ),
            'unread_count' => $this->when(isset($this->unread_count), $this->unread_count),
            'my_role' => $this->when(isset($this->my_role), $this->my_role),
            'last_message' => $this->whenLoaded('latestMessage', function () {
                if (! $this->latestMessage) {
                    return null;
                }

                return new ConnectMessageResource($this->latestMessage->loadMissing('sender'));
            }),
            'members' => $this->whenLoaded('members', function () {
                return $this->members->map(function ($member) {
                    // BelongsToMany User (+ pivot) ou Membership
                    if ($member instanceof \App\Models\User) {
                        return [
                            'id' => $member->id,
                            'user_id' => $member->id,
                            'nom' => $member->name,
                            'name' => $member->name,
                            'email' => $member->email,
                            'code_role' => $member->code_role,
                            'entite' => $member->entite,
                            'role' => $member->pivot?->role ?? 'member',
                            'joined_at' => $member->pivot?->joined_at,
                            'last_read_at' => $member->pivot?->last_read_at,
                            'user' => [
                                'id' => $member->id,
                                'name' => $member->name,
                                'nom' => $member->name,
                                'email' => $member->email,
                                'code_role' => $member->code_role,
                                'entite' => $member->entite,
                            ],
                        ];
                    }

                    return [
                        'id' => $member->id,
                        'user_id' => $member->user_id,
                        'role' => $member->role,
                        'joined_at' => $member->joined_at?->toIso8601String(),
                        'last_read_at' => $member->last_read_at?->toIso8601String(),
                        'user' => $member->relationLoaded('user') && $member->user ? [
                            'id' => $member->user->id,
                            'name' => $member->user->name,
                            'nom' => $member->user->name,
                            'email' => $member->user->email,
                            'code_role' => $member->user->code_role,
                            'entite' => $member->user->entite,
                        ] : null,
                    ];
                })->values();
            }),
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
        ];
    }
}
