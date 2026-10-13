<?php

namespace App\Events;

use App\Http\Resources\ConnectMessageResource;
use App\Models\ConnectMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast immédiat d'un message OPN Connect (canal privé).
 * Driver: BROADCAST_CONNECTION=reverb (+ serveur `php artisan reverb:start`).
 * Fallback front: polling after_id.
 */
class ConnectMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ConnectMessage $message)
    {
        $this->message->loadMissing('sender');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('connect.conversation.'.$this->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'connect.message.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->message->conversation_id,
            'message' => (new ConnectMessageResource($this->message))->resolve(),
        ];
    }
}
