<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UrgenceSanitaire;
use App\Models\AntenneProvinciale;
use App\Models\AuditLog;
use App\Services\OpnAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UrgenceController extends Controller
{
    public function __construct(protected OpnAuthorizationService $authService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Non authentifie.'], 401);
        }

        $query = UrgenceSanitaire::with('antenne');
        $codeRole = $user->code_role ?? '';

        if ($codeRole === 'CHEF_ANTENNE_PROVINCIALE' || $codeRole === 'PHARMACIEN_CHEF_ANTENNE') {
            $query->where('antenne_id', $user->province_id);
        } elseif ($codeRole === 'GESTIONNAIRE_LOGISTIQUE' || $codeRole === 'CHEF_CONVOI_SECURITE') {
            $query->whereIn('type_urgence', ['LOGISTIQUE', 'TERRAIN', 'URGENCE_LOGISTIQUE', 'URGENCE_TERRAIN']);
        } elseif ($codeRole === 'ACHATS' || $codeRole === 'APPRO_NATIONAL' || $codeRole === 'COLLABORATEUR_APPRO') {
            $query->whereIn('type_urgence', [
                'PHARMACEUTIQUE', 'APPROVISIONNEMENT', 'STOCK',
                'URGENCE_PHARMACEUTIQUE', 'URGENCE_STOCK', 'URGENCE_APPROVISIONNEMENT',
            ]);
        } else {
            $query = $this->authService->applyScope($query, $user, 'antenne_id');
        }

        if ($request->has('gravite') && $request->query('gravite') !== 'ALL') {
            $query->where('gravite', $request->query('gravite'));
        }

        $urgences = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'count' => $urgences->count(),
            'urgences' => $urgences,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Non authentifie.'], 401);
        }

        $validated = $request->validate([
            'antenne_id' => 'required|exists:antennes_provinciales,id',
            'titre' => 'required|string|max:255',
            'lieu_precis' => 'required|string|max:255',
            'type_urgence' => 'required|string',
            'gravite' => 'required|string',
            'description' => 'required|string',
        ]);

        // Antenne-scoped users cannot declare outside their province
        $scope = OpnAuthorizationService::ROLES_PERMISSIONS[$user->code_role]['scope'] ?? 'PROVINCE';
        if (in_array($scope, ['ANTENNA', 'PROVINCE'], true)
            && $user->province_id
            && (int) $validated['antenne_id'] !== (int) $user->province_id) {
            return response()->json([
                'success' => false,
                'message' => 'Acces refuse : declaration limitee a votre antenne provinciale.',
            ], 403);
        }

        $antenne = AntenneProvinciale::findOrFail($validated['antenne_id']);
        $codeIncident = 'URG-2026-' . random_int(100, 999);

        $urgence = UrgenceSanitaire::create([
            'code_incident' => $codeIncident,
            'antenne_id' => $antenne->id,
            'titre' => $validated['titre'],
            'lieu_precis' => $validated['lieu_precis'],
            'type_urgence' => $validated['type_urgence'],
            'gravite' => $validated['gravite'],
            'statut' => 'NOUVEAU',
            'declarant_nom' => $user->name,
            'description' => $validated['description'],
            'equipe_affectee' => 'Cellule de Crise OPN Libreville / Owendo',
            'action_engagee' => 'Alerte transmise en haute priorite au COD Libreville.',
            'temps_prise_en_charge_minutes' => 0,
            'latitude' => $antenne->latitude,
            'longitude' => $antenne->longitude,
        ]);

        AuditLog::create([
            'auteur_nom' => $user->name,
            'role' => $user->role ?? $user->code_role ?? 'Agent OPN',
            'action' => 'DECLENCHEMENT_ALERTE_ROUGE',
            'details' => "Emission de l'alerte {$codeIncident} ({$validated['titre']}) pour l'antenne {$antenne->nom}.",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Alerte d\'urgence sanitaire declenchee et transmise au COD Libreville.',
            'urgence' => $urgence->load('antenne'),
        ], 201);
    }

    public function resolve(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Non authentifie.'], 401);
        }

        if (!$this->authService->can($user, 'emergency.resolve')
            && !$this->authService->can($user, 'emergency.manage')
            && !in_array($user->code_role, ['DG', 'DIR', 'COORDINATEUR_URGENCES', 'SUPER_ADMIN'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Acces refuse : resolution reservee a la cellule de crise / direction.',
            ], 403);
        }

        $urgence = UrgenceSanitaire::findOrFail($id);

        $urgence->update([
            'statut' => 'RESOLU',
            'action_engagee' => $urgence->action_engagee . " • Cloture et resolu par {$user->name}.",
        ]);

        AuditLog::create([
            'auteur_nom' => $user->name,
            'role' => $user->role ?? $user->code_role ?? 'Agent OPN',
            'action' => 'RESOLUTION_URGENCE',
            'details' => "Resolution de l'incident {$urgence->code_incident}.",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Incident d\'urgence marque comme resolu.',
            'urgence' => $urgence->load('antenne'),
        ]);
    }
}
