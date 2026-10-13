<?php

use App\Models\ConnectConversation;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| OPN Connect private channels
|--------------------------------------------------------------------------
| Only conversation members may authorize connect.conversation.{id}.
| Frontend: Echo.private(`connect.conversation.${id}`) when BROADCAST_CONNECTION=reverb.
| Otherwise poll: GET /api/v1/connect/conversations/{id}/messages?after_id=
*/

Broadcast::channel('connect.conversation.{conversationId}', function ($user, int $conversationId) {
    return ConnectConversation::query()
        ->whereKey($conversationId)
        ->whereHas('members', fn ($q) => $q->where('user_id', $user->id))
        ->exists();
});
