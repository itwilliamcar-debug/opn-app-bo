<?php

namespace App\Services;

use App\Models\AllocationFefo;
use App\Models\AntenneProvinciale;
use App\Models\DemandeApprovisionnement;
use App\Models\LotProduit;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Allocation FEFO (First Expired, First Out) sur lots hub Owendo / antenne.
 */
class FefoAllocationService
{
    /**
     * Réserve les quantités validées d'une demande sur les lots les plus proches de la péremption.
     *
     * @return array{allocations: array<int, array>, manques: array<int, array>, reserved_total: int}
     */
    public function reserveForDemande(DemandeApprovisionnement $demande, ?int $antenneId = null): array
    {
        $demande->loadMissing(['lignes.produit']);
        $hubId = $antenneId ?: AntenneProvinciale::where('code_antenne', 'EST')->value('id');

        if (AllocationFefo::where('demande_id', $demande->id)->where('statut', 'RESERVEE')->exists()) {
            throw new RuntimeException('Des allocations FEFO RESERVEE existent déjà pour cette demande.');
        }

        $allocations = [];
        $manques = [];
        $reservedTotal = 0;

        DB::transaction(function () use ($demande, $hubId, &$allocations, &$manques, &$reservedTotal) {
            foreach ($demande->lignes as $ligne) {
                $need = (int) ($ligne->quantite_validee ?: $ligne->quantite_demandee);
                if ($need <= 0) {
                    continue;
                }

                $remaining = $need;
                $lots = LotProduit::query()
                    ->where('produit_id', $ligne->produit_id)
                    ->where('is_quarantine', false)
                    ->where('quantite_disponible', '>', 0)
                    ->when($hubId, fn ($q) => $q->where('antenne_id', $hubId))
                    ->orderBy('date_peremption', 'asc')
                    ->lockForUpdate()
                    ->get();

                foreach ($lots as $lot) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $take = min($remaining, (int) $lot->quantite_disponible);
                    if ($take <= 0) {
                        continue;
                    }

                    $lot->quantite_disponible = (int) $lot->quantite_disponible - $take;
                    $lot->quantite_reservee = (int) $lot->quantite_reservee + $take;
                    if ($lot->jours_avant_peremption <= 90) {
                        $lot->statut_fefo = 'ALERTE_PEREMPTION';
                    } elseif ($lot->jours_avant_peremption <= 180) {
                        $lot->statut_fefo = 'PRIORITAIRE';
                    }
                    $lot->save();

                    $alloc = AllocationFefo::create([
                        'demande_id' => $demande->id,
                        'ligne_demande_id' => $ligne->id,
                        'lot_produit_id' => $lot->id,
                        'quantite' => $take,
                        'statut' => 'RESERVEE',
                    ]);

                    $allocations[] = [
                        'allocation_id' => $alloc->id,
                        'lot_id' => $lot->id,
                        'numero_lot' => $lot->numero_lot,
                        'date_peremption' => optional($lot->date_peremption)->format('Y-m-d'),
                        'produit_id' => $ligne->produit_id,
                        'quantite' => $take,
                    ];
                    $reservedTotal += $take;
                    $remaining -= $take;
                }

                if ($remaining > 0) {
                    $manques[] = [
                        'ligne_demande_id' => $ligne->id,
                        'produit_id' => $ligne->produit_id,
                        'designation' => $ligne->produit?->designation,
                        'quantite_manquante' => $remaining,
                    ];
                }
            }
        });

        return [
            'allocations' => $allocations,
            'manques' => $manques,
            'reserved_total' => $reservedTotal,
        ];
    }

    /**
     * Confirme la consommation (sortie magasin) : retire les quantités réservées.
     */
    public function confirmForDemande(DemandeApprovisionnement $demande): int
    {
        $consumed = 0;

        DB::transaction(function () use ($demande, &$consumed) {
            $rows = AllocationFefo::where('demande_id', $demande->id)
                ->where('statut', 'RESERVEE')
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $lot = LotProduit::where('id', $row->lot_produit_id)->lockForUpdate()->first();
                if ($lot) {
                    $lot->quantite_reservee = max(0, (int) $lot->quantite_reservee - (int) $row->quantite);
                    $lot->save();
                }
                $row->update(['statut' => 'CONSOMMEE']);
                $consumed += (int) $row->quantite;
            }
        });

        return $consumed;
    }

    /**
     * Libère les réservations (rejet / annulation).
     */
    public function releaseForDemande(DemandeApprovisionnement $demande): int
    {
        $released = 0;

        DB::transaction(function () use ($demande, &$released) {
            $rows = AllocationFefo::where('demande_id', $demande->id)
                ->where('statut', 'RESERVEE')
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $lot = LotProduit::where('id', $row->lot_produit_id)->lockForUpdate()->first();
                if ($lot) {
                    $lot->quantite_reservee = max(0, (int) $lot->quantite_reservee - (int) $row->quantite);
                    $lot->quantite_disponible = (int) $lot->quantite_disponible + (int) $row->quantite;
                    $lot->save();
                }
                $row->update(['statut' => 'LIBEREE']);
                $released += (int) $row->quantite;
            }
        });

        return $released;
    }
}
