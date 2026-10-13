<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StructureSanitaire;
use App\Models\AntenneProvinciale;
use App\Models\AuditLog;
use App\Services\OpnAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StructureController extends Controller
{
    protected OpnAuthorizationService $authService;

    public function __construct(OpnAuthorizationService $authService)
    {
        $this->authService = $authService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = StructureSanitaire::with('antenne');

        if ($user) {
            $query = $this->authService->applyScope($query, $user, 'antenne_id');
        } elseif ($request->has('antenne_id')) {
            $query->where('antenne_id', $request->query('antenne_id'));
        }

        if ($request->has('type_structure') && $request->query('type_structure') !== 'ALL') {
            $query->where('type_structure', $request->query('type_structure'));
        }

        $structures = $query->orderBy('nom', 'asc')->get();

        return response()->json([
            'success' => true,
            'count' => $structures->count(),
            'structures' => $structures,
        ]);
    }

    /**
     * Liste des antennes provinciales (pour émission de demandes / CMM).
     */
    public function antennes(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = AntenneProvinciale::query()->orderBy('nom');

        if ($user) {
            $codeRole = $user->code_role ?? '';
            $roleConfig = OpnAuthorizationService::ROLES_PERMISSIONS[$codeRole] ?? null;
            $scope = $roleConfig['scope'] ?? 'PROVINCE';

            if (! in_array($scope, ['GLOBAL', 'NATIONAL', 'SERVICE'], true) && $codeRole !== 'SUPER_ADMIN') {
                $antenneId = $user->province_id;
                if ($scope === 'STRUCTURE' && $user->structure_id) {
                    $antenneId = StructureSanitaire::where('id', $user->structure_id)->value('antenne_id') ?: $antenneId;
                }
                if ($antenneId) {
                    $query->where('id', $antenneId);
                }
            }
        }

        $antennes = $query->get();

        return response()->json([
            'success' => true,
            'count' => $antennes->count(),
            'antennes' => $antennes,
        ]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $structure = StructureSanitaire::with(['antenne', 'demandes', 'livraisons'])->findOrFail($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifie. Session Sanctum requise.',
            ], 401);
        }
        if (!$this->authService->can($user, 'structures.view.antenna', $structure->antenne_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Acces refuse : Cette structure sanitaire n\'appartient pas a votre perimetre provincial autorise.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'structure' => $structure,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code_structure' => 'required|string|unique:structures_sanitaires,code_structure',
            'nom' => 'required|string|max:200',
            'type_structure' => 'required|string',
            'antenne_id' => 'required|exists:antennes_provinciales,id',
            'province_code' => 'required|string',
            'departement' => 'required|string',
            'ville' => 'required|string',
            'responsable_nom' => 'nullable|string',
            'contact_urgence' => 'required|string',
            'email' => 'nullable|email',
            'adresse' => 'nullable|string',
        ]);

        $structure = StructureSanitaire::create($validated);

        AuditLog::create([
            'auteur_nom' => $request->user() ? $request->user()->name : 'SuperAdmin IT',
            'role' => $request->user() ? $request->user()->role : 'ADMIN',
            'action' => 'CREATION_STRUCTURE_SANITAIRE',
            'details' => "Création de la structure {$structure->nom} ({$structure->code_structure}) dans le département {$structure->departement}.",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Structure sanitaire enregistrée avec succès.',
            'structure' => $structure,
        ], 201);
    }
}
