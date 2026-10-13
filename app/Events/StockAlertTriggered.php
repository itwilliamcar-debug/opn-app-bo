<?php

namespace App\Events;

use App\Models\ProduitPharmaceutique;
use App\Models\AntenneProvinciale;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockAlertTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ProduitPharmaceutique $produit;
    public AntenneProvinciale $antenne;
    public int $stockActuel;
    public string $niveauCriticite;

    public function __construct(ProduitPharmaceutique $produit, AntenneProvinciale $antenne, int $stockActuel, string $niveauCriticite = 'CRITIQUE')
    {
        $this->produit = $produit;
        $this->antenne = $antenne;
        $this->stockActuel = $stockActuel;
        $this->niveauCriticite = $niveauCriticite;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('opn-stock-alerts'),
            new Channel("antenne.{$this->antenne->id}.stocks"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'stock.critical_alert';
    }
}
