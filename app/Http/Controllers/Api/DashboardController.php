<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AntenneProvinciale;
use App\Models\DemandeApprovisionnement;
use App\Models\ProduitPharmaceutique;
use App\Models\UrgenceSanitaire;
use App\Services\OpnAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected OpnAuthorizationService $authService)
    {
    }

    public function getNationalOverview(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Non authentifie.'], 401);
        }

        if (!$this->authService->can($user, 'dashboard.national.view')
            && !$this->authService->can($user, 'antenna.dashboard.view')
            && !$this->authService->can($user, 'logistics.dashboard.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Acces refuse au tableau de bord.',
            ], 403);
        }

        $antennesQuery = AntenneProvinciale::with(['lots']);
        $antennesQuery = $this->authService->applyScope($antennesQuery, $user, 'id');
        $antennes = $antennesQuery->get();

        $demandesQuery = DemandeApprovisionnement::where('statut', '!=', 'RECU_CONFORME');
        $demandesQuery = $this->authService->applyScope($demandesQuery, $user, 'antenne_id');

        $urgencesQuery = UrgenceSanitaire::where('statut', '!=', 'RESOLU');
        $urgencesQuery = $this->authService->applyScope($urgencesQuery, $user, 'antenne_id');

        $totalStockXAF = $antennes->sum('stock_valeur_xaf');
        $totalCommandesEnCours = $demandesQuery->count();
        $totalAlertesCritiques = $urgencesQuery->count();

        $produitsCritiques = ProduitPharmaceutique::where(function ($q) {
            $q->where('statut', 'STOCK_CRITIQUE')->orWhere('statut', 'RUPTURE');
        })->get(['id', 'code_produit', 'designation', 'dci', 'statut', 'stock_national']);

        return response()->json([
            'success' => true,
            'kpis' => [
                'valeur_stock_national_xaf' => $totalStockXAF,
                'total_antennes_connectees' => $antennes->count(),
                'commandes_en_transit' => $totalCommandesEnCours,
                'alertes_actives' => $totalAlertesCritiques,
                'couverture_moyenne_jours' => round((float) $antennes->avg('couverture_jours'), 1),
            ],
            'antennes' => $antennes,
            'produits_critiques' => $produitsCritiques,
            'scope' => OpnAuthorizationService::ROLES_PERMISSIONS[$user->code_role]['scope'] ?? 'PROVINCE',
        ]);
    }
}
