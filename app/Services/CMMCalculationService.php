<?php

namespace App\Services;

class CMMCalculationService
{
    /**
     * Calcule le besoin prévisionnel en réapprovisionnement pour une antenne provinciale
     *
     * @param array $params
     * @return array
     */
    public function calculateNeed(array $params): array
    {
        $cmmLocale = $params['cmm_locale'] ?? 1000;
        $stockDispo = $params['stock_dispo'] ?? 0;
        $enTransit = $params['en_transit'] ?? 0;
        $leadTimeJours = $params['lead_time_jours'] ?? 5;
        $stockSecuriteJours = $params['stock_securite_jours'] ?? 45;
        $coeffSaisonnier = $params['coeff_saisonnier'] ?? 1.0;
        $prixUnitaireXAF = $params['prix_unitaire_xaf'] ?? 0;

        $cmmAjustee = $cmmLocale * $coeffSaisonnier;
        $consoJournaliere = $cmmAjustee / 30;

        // Stock de sécurité requis en unités
        $stockSecuriteUnites = (int) round($consoJournaliere * $stockSecuriteJours);

        // Consommation durant le délai d'acheminement
        $consoPendantLeadTime = (int) round($consoJournaliere * $leadTimeJours);

        // Stock cible
        $stockCible = $consoPendantLeadTime + $stockSecuriteUnites;

        // Stock net
        $stockNet = $stockDispo + $enTransit;

        // Quantité recommandée
        $quantiteRecommandee = max(0, $stockCible - $stockNet);

        // Autonomie restante en jours
        $autonomieJours = $consoJournaliere > 0 
            ? (int) round($stockDispo / ($cmmLocale / 30)) 
            : 999;

        // Diagnostic du niveau de criticité
        $niveauCriticite = 'NORMAL';
        if ($stockDispo === 0) {
            $niveauCriticite = 'RUPTURE';
        } elseif ($autonomieJours <= $leadTimeJours) {
            $niveauCriticite = 'CRITIQUE';
        } elseif ($autonomieJours <= ($leadTimeJours + ($stockSecuriteJours / 2))) {
            $niveauCriticite = 'VIGILANCE';
        }

        // Valorisation en FCFA (XAF)
        $coutTotalXAF = $quantiteRecommandee * $prixUnitaireXAF;

        return [
            'cmm_ajustee' => (int) round($cmmAjustee),
            'stock_securite_unites' => $stockSecuriteUnites,
            'conso_pendant_lead_time' => $consoPendantLeadTime,
            'stock_cible' => $stockCible,
            'quantite_recommandee' => $quantiteRecommandee,
            'autonomie_jours' => $autonomieJours,
            'niveau_criticite' => $niveauCriticite,
            'cout_total_xaf' => $coutTotalXAF,
        ];
    }
}
