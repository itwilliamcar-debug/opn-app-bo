<?php

namespace App\Services;

use App\Models\CommandeTerritoriale;
use App\Models\FluxPieceCommerciale;
use App\Models\FluxPriseEnChargeSiege;
use Illuminate\Support\Facades\DB;

class FluxPieceCommercialeService
{
    /**
     * Génère un bon de commande + une facture pour la vague de prise en charge.
     *
     * @param  array<int, array{row: \App\Models\LigneCommandeTerritoriale, qte: int}>  $plan
     * @return array{bon_commande: FluxPieceCommerciale, facture: FluxPieceCommerciale}
     */
    public function genererApresPriseEnCharge(
        CommandeTerritoriale $commande,
        FluxPriseEnChargeSiege $prise,
        array $plan,
        object $user,
        int $quantitePrise,
        int $quantiteReliquat,
        ?string $note = null
    ): array {
        $commande->loadMissing('antenne');
        $now = now();
        $lignesPayload = [];
        $montant = 0;
        $demandeTotale = 0;

        foreach ($plan as $item) {
            $row = $item['row'];
            $qte = (int) $item['qte'];
            $prix = (float) ($row->prix_unitaire_xaf ?? 0);
            $ligneMontant = round($prix * $qte, 2);
            $montant += $ligneMontant;
            $demandeTotale += (int) $row->quantite_demandee;
            $lignesPayload[] = [
                'produit_id' => $row->produit_id,
                'designation' => $row->designation,
                'quantite_demandee' => (int) $row->quantite_demandee,
                'quantite_prise' => $qte,
                'quantite_reliquat' => max(0, (int) $row->quantite_demandee - (int) $row->quantite_prise_en_charge),
                'prix_unitaire_xaf' => $prix,
                'montant_ligne_xaf' => $ligneMontant,
            ];
        }

        $base = [
            'commande_id' => $commande->id,
            'prise_en_charge_id' => $prise->id,
            'antenne_id' => $commande->antenne_id,
            'demandeur_user_id' => $commande->demandeur_user_id,
            'demandeur_nom' => $commande->demandeur_nom,
            'partenaire_nom' => $commande->partenaire_nom,
            'antenne_nom' => $commande->antenne?->nom,
            'numero_commande' => $commande->numero_commande,
            'quantite_prise_totale' => $quantitePrise,
            'quantite_reliquat_totale' => $quantiteReliquat,
            'quantite_demandee_totale' => $demandeTotale,
            'montant_ht_xaf' => $montant,
            'montant_ttc_xaf' => $montant,
            'annee' => (int) $now->format('Y'),
            'mois' => (int) $now->format('n'),
            'emitted_by_nom' => $user->name,
            'emitted_at' => $now,
            'note' => $note,
        ];

        return DB::transaction(function () use ($base, $lignesPayload, $now) {
            $bc = $this->creerPiece(FluxPieceCommerciale::TYPE_BC, 'BC', $base, $lignesPayload, $now);
            $facture = $this->creerPiece(FluxPieceCommerciale::TYPE_FACTURE, 'FAC', $base, $lignesPayload, $now);

            return [
                'bon_commande' => $bc->load('lignes'),
                'facture' => $facture->load('lignes'),
            ];
        });
    }

    private function creerPiece(string $type, string $prefix, array $base, array $lignes, $now): FluxPieceCommerciale
    {
        $seq = FluxPieceCommerciale::where('type', $type)
            ->where('annee', (int) $now->format('Y'))
            ->count() + 1;

        $piece = FluxPieceCommerciale::create(array_merge($base, [
            'type' => $type,
            'numero' => sprintf('%s-%s-%04d', $prefix, $now->format('Ymd'), $seq),
        ]));

        foreach ($lignes as $ligne) {
            $piece->lignes()->create($ligne);
        }

        return $piece;
    }
}
