<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConnectMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerId = $request->user()?->id;
        $grouped = $this->buildReactionsPayload($viewerId);
        $myReaction = null;
        foreach ($grouped as $row) {
            if (! empty($row['reacted_by_me'])) {
                $myReaction = $row['emoji'];
                break;
            }
        }

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'body' => $this->body,
            'emoji_code' => $this->emoji_code,
            'audio_path' => $this->audio_path,
            'audio_url' => $this->audio_url,
            'audio_duration_sec' => $this->audio_duration_sec,
            'meta' => $this->meta,
            'reactions' => $grouped,
            'my_reaction' => $myReaction,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'sender' => $this->whenLoaded('sender', function () {
                return [
                    'id' => $this->sender->id,
                    'name' => $this->sender->name,
                    'email' => $this->sender->email,
                    'code_role' => $this->sender->code_role,
                    'entite' => $this->sender->entite,
                ];
            }),
        ];
    }

    /**
     * @return array<int, array{emoji: string, count: int, user_ids: array<int, int>, reacted_by_me: bool}>
     */
    protected function buildReactionsPayload(mixed $viewerId): array
    {
        // Table dédiée optionnelle (non utilisée en local)
        $raw = is_array($this->meta) ? ($this->meta['reactions'] ?? []) : [];
        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $map = [];
        foreach ($raw as $key => $value) {
            if (is_string($key) && ! is_int($key)) {
                $map[$key] = array_values(array_map('intval', (array) $value));
            } elseif (is_array($value) && ! empty($value['emoji'])) {
                $map[$value['emoji']] = array_values(array_map(
                    'intval',
                    (array) ($value['user_ids'] ?? [])
                ));
            }
        }

        $out = [];
        foreach ($map as $emoji => $userIds) {
            if ($userIds === []) {
                continue;
            }
            $out[] = [
                'emoji' => (string) $emoji,
                'count' => count($userIds),
                'user_ids' => $userIds,
                'reacted_by_me' => $viewerId
                    ? in_array((int) $viewerId, $userIds, true)
                    : false,
            ];
        }

        return $out;
    }
}
