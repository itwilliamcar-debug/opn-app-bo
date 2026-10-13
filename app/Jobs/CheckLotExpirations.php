<?php

namespace App\Jobs;

use App\Events\StockAlertTriggered;
use App\Models\LotProduit;
use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Passe les lots proches de la péremption en ALERTE_PEREMPTION et déclenche StockAlertTriggered.
 */
class CheckLotExpirations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $alertDays = 90)
    {
    }

    public function handle(): void
    {
        $deadline = now()->addDays($this->alertDays)->toDateString();

        $lots = LotProduit::with(['produit', 'antenne'])
            ->where('is_quarantine', false)
            ->where('quantite_disponible', '>', 0)
            ->whereDate('date_peremption', '<=', $deadline)
            ->where('statut_fefo', '!=', 'ALERTE_PEREMPTION')
            ->get();

        foreach ($lots as $lot) {
            $lot->update(['statut_fefo' => 'ALERTE_PEREMPTION']);

            if ($lot->produit && $lot->antenne) {
                try {
                    event(new StockAlertTriggered(
                        $lot->produit,
                        $lot->antenne,
                        (int) $lot->quantite_disponible,
                        'PEREMPTION'
                    ));
                } catch (\Throwable $e) {
                    Log::warning('StockAlertTriggered broadcast failed: '.$e->getMessage());
                }
            }

            AuditLog::create([
                'auteur_nom' => 'SYSTEME_FEFO',
                'role' => 'JOB',
                'action' => 'ALERTE_PEREMPTION_LOT',
                'details' => "Lot {$lot->numero_lot} produit #{$lot->produit_id} expire le {$lot->date_peremption->format('Y-m-d')} (stock {$lot->quantite_disponible}).",
                'ip_address' => '127.0.0.1',
            ]);
        }
    }
}
