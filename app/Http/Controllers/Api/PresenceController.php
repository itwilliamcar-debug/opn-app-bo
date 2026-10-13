<?php

namespace App\Http\Controllers\Api;

use App\Events\ConnectMessageCreated;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConnectMessageResource;
use App\Models\AntenneProvinciale;
use App\Models\ConnectConversation;
use App\Models\ConnectConversationMember;
use App\Models\ConnectMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Présence intranet OPN — collègues en ligne filtrés par province (antenne).
 */
class PresenceController extends Controller
{
    /** Fenêtre d'activité considérée « en ligne » (minutes). */
    public const ONLINE_WINDOW_MINUTES = 3;

    /**
     * POST /presence/heartbeat — marque l'utilisateur comme actif.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['last_seen_at' => now()])->save();

        return response()->json([
            'success' => true,
            'last_seen_at' => $user->last_seen_at?->toIso8601String(),
            'province_id' => $user->province_id,
            'ville' => $user->ville,
        ]);
    }

    /**
     * GET /presence/online — collègues en ligne de la même province uniquement.
     */
    public function online(Request $request): JsonResponse
    {
        $user = $request->user();
        $provinceId = $user->province_id;

        $provinceLabel = $user->ville;
        if (! $provinceLabel && $provinceId) {
            $provinceLabel = AntenneProvinciale::query()->whereKey($provinceId)->value('chef_lieu');
        }

        if (! $provinceId) {
            return response()->json([
                'success' => true,
                'province_id' => null,
                'ville' => $provinceLabel,
                'online_window_minutes' => self::ONLINE_WINDOW_MINUTES,
                'data' => [],
                'message' => 'Aucune province assignée — présence locale indisponible.',
            ]);
        }

        $since = now()->subMinutes(self::ONLINE_WINDOW_MINUTES);

        $colleagues = User::query()
            ->where('province_id', $provinceId)
            ->where('id', '!=', $user->id)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $since)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'code_role',
                'role',
                'entite',
                'ville',
                'province_id',
                'telephone',
                'last_seen_at',
            ])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'nom' => $u->name,
                'email' => $u->email,
                'code_role' => $u->code_role,
                'role' => $u->role,
                'entite' => $u->entite,
                'ville' => $u->ville ?: $provinceLabel,
                'province_id' => $u->province_id,
                'telephone' => $u->telephone,
                'last_seen_at' => $u->last_seen_at?->toIso8601String(),
                'online' => true,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'province_id' => $provinceId,
            'ville' => $provinceLabel,
            'online_window_minutes' => self::ONLINE_WINDOW_MINUTES,
            'count' => $colleagues->count(),
            'data' => $colleagues,
        ]);
    }

    /**
     * POST /presence/note — envoie une note privée à un collègue de la même province.
     */
    public function sendNote(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'body' => ['required', 'string', 'min:1', 'max:4000'],
        ]);

        $recipient = User::query()->findOrFail($validated['recipient_id']);
        $this->assertSameProvince($user, $recipient);

        $conversation = $this->findOrCreateDirect($user, $recipient);
        $message = $this->persistText($conversation, $user->id, $validated['body']);

        return response()->json([
            'success' => true,
            'message' => 'Note envoyée.',
            'conversation_id' => $conversation->id,
            'data' => new ConnectMessageResource($message),
        ], Response::HTTP_CREATED);
    }

    /**
     * POST /presence/file — envoie un fichier (note + pièce jointe) à un collègue de la province.
     */
    public function sendFile(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'file' => ['required', 'file', 'max:10240'],
            'body' => ['nullable', 'string', 'max:1000'],
        ]);

        $recipient = User::query()->findOrFail((int) $validated['recipient_id']);
        $this->assertSameProvince($user, $recipient);

        $file = $request->file('file');
        $path = $file->store('connect/files', 'public');
        $original = $file->getClientOriginalName();

        $conversation = $this->findOrCreateDirect($user, $recipient);
        $body = trim((string) ($validated['body'] ?? ''));
        $body = $body !== ''
            ? $body."\n📎 Fichier : ".$original
            : '📎 Fichier partagé : '.$original;

        $message = $this->persistText($conversation, $user->id, $body, [
            'file_path' => $path,
            'file_name' => $original,
            'file_url' => asset('storage/'.$path),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fichier envoyé.',
            'conversation_id' => $conversation->id,
            'data' => new ConnectMessageResource($message),
        ], Response::HTTP_CREATED);
    }

    private function assertSameProvince(User $sender, User $recipient): void
    {
        if (! $sender->province_id || (int) $sender->province_id !== (int) $recipient->province_id) {
            abort(Response::HTTP_FORBIDDEN, 'Vous ne pouvez contacter que les collègues de votre province / ville antenne.');
        }

        if ((int) $sender->id === (int) $recipient->id) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Destinataire invalide.');
        }
    }

    private function findOrCreateDirect(User $a, User $b): ConnectConversation
    {
        $existing = ConnectConversation::query()
            ->where('type', 'direct')
            ->whereHas('members', fn ($q) => $q->where('user_id', $a->id))
            ->whereHas('members', fn ($q) => $q->where('user_id', $b->id))
            ->withCount('members')
            ->get()
            ->first(fn ($c) => (int) $c->members_count === 2);

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($a, $b) {
            $conversation = ConnectConversation::query()->create([
                'uuid' => (string) Str::uuid(),
                'type' => 'direct',
                'title' => $a->name.' ↔ '.$b->name,
                'description' => 'Discussion privée province',
                'jitsi_room' => 'opn-connect-'.Str::lower(Str::random(24)),
                'created_by' => $a->id,
                'last_message_at' => now(),
            ]);

            ConnectConversationMember::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $a->id,
                'role' => 'owner',
                'joined_at' => now(),
            ]);
            ConnectConversationMember::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $b->id,
                'role' => 'member',
                'joined_at' => now(),
            ]);

            return $conversation;
        });
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function persistText(
        ConnectConversation $conversation,
        int $userId,
        string $body,
        ?array $meta = null,
    ): ConnectMessage {
        $message = ConnectMessage::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $userId,
            'type' => 'text',
            'body' => $body,
            'meta' => $meta,
        ]);

        $conversation->update(['last_message_at' => now()]);
        $message->loadMissing('sender');
        event(new ConnectMessageCreated($message));

        return $message;
    }
}
