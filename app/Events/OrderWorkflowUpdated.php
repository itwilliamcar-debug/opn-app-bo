<?php

namespace App\Events;

use App\Models\DemandeApprovisionnement;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderWorkflowUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public DemandeApprovisionnement $demande;
    public string $nouvelleEtape;

    public function __construct(DemandeApprovisionnement $demande, string $nouvelleEtape)
    {
        $this->demande = $demande;
        $this->nouvelleEtape = $nouvelleEtape;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('opn-supply-orders'),
            new Channel("antenne.{$this->demande->antenne_id}.orders"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.status_updated';
    }
}
