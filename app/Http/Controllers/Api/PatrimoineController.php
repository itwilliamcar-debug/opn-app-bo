<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Amortissement;
use App\Models\AntenneProvinciale;
use App\Models\BienPatrimoine;
use App\Models\CategorieBien;
use App\Models\Siege;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatrimoineController extends Controller
{
    private const ROLES_PATRIMOINE = ['DG', 'DIR', 'DAF', 'APPRO_NATIONAL', 'SUPER_ADMIN', 'ADMIN_METIER'];

    public function index(Request $request): JsonResponse
    {
        $this->assertDirection($request);

        return response()->json([
            'success' => true,
            'biens' => BienPatrimoine::with(['categorie', 'amortissement', 'antenne', 'siege', 'detenteur'])->orderByDesc('id')->get(),
            'categories' => CategorieBien::orderBy('libelle')->get(),
            'amortissements' => Amortissement::orderBy('duree_mois')->get(),
            'agents' => Agent::orderBy('nom')->get(['id', 'nom', 'fonction', 'antenne_id', 'siege_id', 'direction_id', 'service_id']),
            'siege' => Siege::query()->orderBy('id')->first(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertDirection($request);
        $data = $this->validateBien($request);
        $data = $this->rattacherSiege($data);
        $data['code_bien'] = 'PAT-'.now()->format('Ymd').'-'.str_pad((string) (BienPatrimoine::count() + 1), 4, '0', STR_PAD_LEFT);
        $bien = BienPatrimoine::create($data);

        return response()->json(['success' => true, 'bien' => $this->charge($bien)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->assertDirection($request);
        $bien = BienPatrimoine::findOrFail($id);
        $data = $this->validateBien($request, false);
        if (array_key_exists('antenne_id', $data) || array_key_exists('siege_id', $data)) {
            $data = $this->rattacherSiege(array_merge($bien->only(['antenne_id', 'siege_id']), $data));
        }
        $bien->update($data);

        return response()->json(['success' => true, 'bien' => $this->charge($bien->fresh())]);
    }

    public function remettre(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'detenteur_id' => 'required|exists:agents,id',
            'date_remise' => 'required|date',
            'localisation' => 'nullable|string|max:200',
        ]);
        $this->assertDirection($request);
        $bien = BienPatrimoine::findOrFail($id);
        $bien->update([
            'detenteur_id' => $data['detenteur_id'],
            'date_remise' => $data['date_remise'],
            'date_retour' => null,
            'localisation' => $data['localisation'] ?? $bien->localisation,
        ]);

        return response()->json(['success' => true, 'bien' => $this->charge($bien->fresh())]);
    }

    private function validateBien(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'designation' => ($creating ? 'required' : 'sometimes').'|string|max:200',
            'categorie_id' => ($creating ? 'required' : 'sometimes').'|exists:categories_biens,id',
            'description' => 'nullable|string|max:1000',
            'valeur_acquisition' => ($creating ? 'required' : 'sometimes').'|numeric|min:0',
            'date_acquisition' => ($creating ? 'required' : 'sometimes').'|date',
            'amortissement_id' => ($creating ? 'required' : 'sometimes').'|exists:amortissements,id',
            'etat' => 'nullable|string|max:30',
            'antenne_id' => 'nullable|exists:antennes_provinciales,id',
            'siege_id' => 'nullable|exists:sieges,id',
            'localisation' => 'nullable|string|max:200',
            'detenteur_id' => 'nullable|exists:agents,id',
            'date_remise' => 'nullable|date',
            'observations' => 'nullable|string|max:1000',
        ]);
    }

    private function rattacherSiege(array $data): array
    {
        if (! empty($data['antenne_id'])) {
            $data['siege_id'] = AntenneProvinciale::where('id', $data['antenne_id'])->value('siege_id');
        } elseif (empty($data['siege_id'])) {
            $data['siege_id'] = Siege::query()->value('id');
        }

        return $data;
    }

    private function charge(BienPatrimoine $bien): BienPatrimoine
    {
        return $bien->load(['categorie', 'amortissement', 'antenne', 'siege', 'detenteur']);
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
