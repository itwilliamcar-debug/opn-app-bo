<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BienPatrimoine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatrimoineController extends Controller
{
    private const ROLES_PATRIMOINE = ['DG', 'DIR', 'DAF', 'APPRO_NATIONAL', 'SUPER_ADMIN'];

    public function index(Request $request): JsonResponse
    {
        $this->assertDirection($request);

        return response()->json([
            'success' => true,
            'biens' => BienPatrimoine::with('antenne')->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertDirection($request);
        $data = $this->validateBien($request);
        $data['code_bien'] = 'PAT-'.now()->format('Ymd').'-'.str_pad((string) (BienPatrimoine::count() + 1), 4, '0', STR_PAD_LEFT);
        $bien = BienPatrimoine::create($data);

        return response()->json(['success' => true, 'bien' => $bien->load('antenne')], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->assertDirection($request);
        $bien = BienPatrimoine::findOrFail($id);
        $bien->update($this->validateBien($request, false));

        return response()->json(['success' => true, 'bien' => $bien->fresh('antenne')]);
    }

    public function remettre(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'detenteur_nom' => 'required|string|max:200',
            'date_remise' => 'required|date',
            'localisation' => 'nullable|string|max:200',
        ]);
        $this->assertDirection($request);
        $bien = BienPatrimoine::findOrFail($id);
        $bien->update([
            'detenteur_nom' => $data['detenteur_nom'],
            'detenteur_user_id' => $request->user()->id,
            'date_remise' => $data['date_remise'],
            'date_retour' => null,
            'localisation' => $data['localisation'] ?? $bien->localisation,
        ]);

        return response()->json(['success' => true, 'bien' => $bien->fresh('antenne')]);
    }

    private function validateBien(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'designation' => ($creating ? 'required' : 'sometimes').'|string|max:200',
            'categorie' => ($creating ? 'required' : 'sometimes').'|string|max:40',
            'description' => 'nullable|string|max:1000',
            'valeur_acquisition' => ($creating ? 'required' : 'sometimes').'|numeric|min:0',
            'date_acquisition' => ($creating ? 'required' : 'sometimes').'|date',
            'duree_amortissement_mois' => 'nullable|integer|min:1|max:600',
            'etat' => 'nullable|string|max:30',
            'antenne_id' => 'nullable|exists:antennes_provinciales,id',
            'localisation' => 'nullable|string|max:200',
            'detenteur_nom' => 'nullable|string|max:200',
            'date_remise' => 'nullable|date',
            'observations' => 'nullable|string|max:1000',
        ]);
    }

    private function assertDirection(Request $request): void
    {
        if (! in_array($request->user()->code_role, self::ROLES_PATRIMOINE, true)) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json(['success' => false, 'message' => 'La gestion du patrimoine est réservée à la direction.'], 403)
            );
        }
    }
}
