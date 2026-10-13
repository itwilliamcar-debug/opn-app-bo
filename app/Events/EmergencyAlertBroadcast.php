<?php

namespace App\Events;

use App\Models\UrgenceSanitaire;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyAlertBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public UrgenceSanitaire $urgence;
    public string $message;

    public function __construct(UrgenceSanitaire $urgence, string $message = '')
    {
        $this->urgence = $urgence;
        $this->message = $message ?: "🚨 ALERTE SANITAIRE CRITIQUE : {$urgence->titre} ({$urgence->lieu_precis})";
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('opn-national-emergencies'),
            new PrivateChannel("antenne.{$this->urgence->antenne_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'emergency.triggered';
    }
}
