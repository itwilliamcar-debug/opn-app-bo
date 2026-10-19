<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AntenneProvinciale;
use App\Models\CommercialProspect;
use App\Models\CommercialVisite;
use App\Models\StructureSanitaire;
use App\Models\TypeStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CommercialController extends Controller
{
    public function referentielOptions(): JsonResponse
    {
        $antennes = AntenneProvinciale::query()
            ->orderBy('nom')
            ->get(['id', 'code_antenne', 'nom', 'chef_lieu', 'ville_antenne'])
            ->map(fn (AntenneProvinciale $a) => [
                'id' => $a->id,
                'code' => $a->code_antenne,
                'nom' => $a->nom,
                'chef_lieu' => $a->chef_lieu,
                'ville' => $a->ville_antenne ?? $a->chef_lieu,
            ])
            ->values();

        $typesStructures = TypeStructure::query()
            ->orderBy('libelle')
            ->get(['id', 'code', 'libelle'])
            ->map(fn (TypeStructure $t) => [
                'id' => $t->id,
                'code' => $t->code,
                'libelle' => $t->libelle,
            ])
            ->values();

        $structures = collect();
        if (Schema::hasTable('structures_sanitaires')) {
            $structures = StructureSanitaire::query()
                ->orderBy('nom')
                ->limit(50)
                ->get(['id', 'nom', 'type_structure', 'ville'])
                ->map(fn (StructureSanitaire $s) => [
                    'id' => $s->id,
                    'nom' => $s->nom,
                    'type' => $s->type_structure,
                    'ville' => $s->ville,
                ])
                ->values();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'antennes' => $antennes,
                'types_structures' => $typesStructures,
                'structures' => $structures,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = CommercialProspect::query()->with('visites')->orderByDesc('id');

        if ($request->filled('antenne') && $request->query('antenne') !== 'ALL') {
            $antenne = $request->query('antenne');
            $query->where(function ($q) use ($antenne) {
                $q->where('antenne_code', $antenne)
                    ->orWhere('province_code', $antenne);
            });
        }

        if ($request->filled('statut') && $request->query('statut') !== 'ALL') {
            $query->where('statut', $request->query('statut'));
        }

        if ($request->has('dormant')) {
            $dormant = filter_var($request->query('dormant'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($dormant !== null) {
                $query->where('is_dormant', $dormant);
            }
        }

        $search = trim((string) $request->query('search', $request->query('q', '')));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('code', 'like', $like)
                    ->orWhere('nom_structure', 'like', $like)
                    ->orWhere('contact_nom', 'like', $like)
                    ->orWhere('localite', 'like', $like)
                    ->orWhere('telephone', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        $prospects = $query->get();

        return response()->json([
            'success' => true,
            'data' => $prospects,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'nullable|string|max:40|unique:commercial_prospects,code',
            'antenne_code' => 'nullable|string|max:40',
            'antenne_nom' => 'nullable|string|max:200',
            'nom_structure' => 'required|string|max:255',
            'type_structure' => 'nullable|string|max:120',
            'province' => 'nullable|string|max:120',
            'province_code' => 'nullable|string|max:20',
            'localite' => 'nullable|string|max:120',
            'region_sanitaire' => 'nullable|string|max:160',
            'contact_nom' => 'nullable|string|max:160',
            'telephone' => 'nullable|string|max:60',
            'email' => 'nullable|email|max:160',
            'adresse' => 'nullable|string|max:255',
            'gps_lat' => 'nullable|numeric',
            'gps_lng' => 'nullable|numeric',
            'photo_url' => 'nullable|string|max:500',
            'statut' => 'nullable|string|max:40',
            'date_creation' => 'nullable|date',
            'commercial_assignee' => 'nullable|string|max:160',
            'commentaire' => 'nullable|string',
            'devis_associes' => 'nullable|array',
            'structure_id' => 'nullable|integer',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        if (empty($data['code'])) {
            $data['code'] = $this->genererCodeProspect($data['antenne_code'] ?? $data['province_code'] ?? 'XX');
        }

        $data['statut'] = $data['statut'] ?? 'IDENTIFIE';
        $data['date_creation'] = $data['date_creation'] ?? now()->toDateString();
        $data['jours_depuis_visite'] = $data['jours_depuis_visite'] ?? 0;
        $data['is_dormant'] = $data['is_dormant'] ?? false;
        $data['user_id'] = $data['user_id'] ?? $request->user()?->id;

        if (! empty($data['structure_id']) && ! Schema::hasTable('structures_sanitaires')) {
            unset($data['structure_id']);
        }

        $prospect = CommercialProspect::create($data);
        $prospect->load('visites');

        return response()->json([
            'success' => true,
            'data' => $prospect,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $prospect = CommercialProspect::with('visites')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $prospect,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $prospect = CommercialProspect::findOrFail($id);

        $data = $request->validate([
            'code' => 'sometimes|string|max:40|unique:commercial_prospects,code,'.$prospect->id,
            'antenne_code' => 'sometimes|nullable|string|max:40',
            'antenne_nom' => 'sometimes|nullable|string|max:200',
            'nom_structure' => 'sometimes|string|max:255',
            'type_structure' => 'sometimes|nullable|string|max:120',
            'province' => 'sometimes|nullable|string|max:120',
            'province_code' => 'sometimes|nullable|string|max:20',
            'localite' => 'sometimes|nullable|string|max:120',
            'region_sanitaire' => 'sometimes|nullable|string|max:160',
            'contact_nom' => 'sometimes|nullable|string|max:160',
            'telephone' => 'sometimes|nullable|string|max:60',
            'email' => 'sometimes|nullable|email|max:160',
            'adresse' => 'sometimes|nullable|string|max:255',
            'gps_lat' => 'sometimes|nullable|numeric',
            'gps_lng' => 'sometimes|nullable|numeric',
            'photo_url' => 'sometimes|nullable|string|max:500',
            'statut' => 'sometimes|string|max:40',
            'date_creation' => 'sometimes|nullable|date',
            'date_derniere_visite' => 'sometimes|nullable|date',
            'jours_depuis_visite' => 'sometimes|integer|min:0',
            'is_dormant' => 'sometimes|boolean',
            'commercial_assignee' => 'sometimes|nullable|string|max:160',
            'commentaire' => 'sometimes|nullable|string',
            'devis_associes' => 'sometimes|nullable|array',
            'client_converti_id' => 'sometimes|nullable|string|max:60',
            'structure_id' => 'sometimes|nullable|integer',
            'user_id' => 'sometimes|nullable|integer|exists:users,id',
        ]);

        $prospect->update($data);
        $prospect->load('visites');

        return response()->json([
            'success' => true,
            'data' => $prospect,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $prospect = CommercialProspect::findOrFail($id);
        $prospect->delete();

        return response()->json([
            'success' => true,
            'message' => 'Prospect supprimé.',
        ]);
    }

    public function storeVisite(Request $request, int $prospectId): JsonResponse
    {
        $prospect = CommercialProspect::findOrFail($prospectId);

        $data = $request->validate([
            'code_visite' => 'nullable|string|max:40|unique:commercial_visites,code_visite',
            'date_visite' => 'nullable|date',
            'commercial' => 'nullable|string|max:160',
            'resultat' => 'nullable|string|max:60',
            'commentaire' => 'nullable|string',
            'prochaine_action' => 'nullable|string|max:500',
            'gps_lat' => 'nullable|numeric',
            'gps_lng' => 'nullable|numeric',
            'photo_url' => 'nullable|string|max:500',
            'statut' => 'nullable|string|max:40',
        ]);

        $visite = DB::transaction(function () use ($prospect, $data, $request) {
            $dateVisite = $data['date_visite'] ?? now()->toDateString();
            $codeVisite = $data['code_visite'] ?? $this->genererCodeVisite();

            $visite = CommercialVisite::create([
                'prospect_id' => $prospect->id,
                'code_visite' => $codeVisite,
                'date_visite' => $dateVisite,
                'commercial' => $data['commercial'] ?? $prospect->commercial_assignee ?? $request->user()?->name,
                'resultat' => $data['resultat'] ?? null,
                'commentaire' => $data['commentaire'] ?? null,
                'prochaine_action' => $data['prochaine_action'] ?? null,
                'gps_lat' => $data['gps_lat'] ?? null,
                'gps_lng' => $data['gps_lng'] ?? null,
                'photo_url' => $data['photo_url'] ?? null,
            ]);

            $updates = [
                'date_derniere_visite' => $dateVisite,
                'jours_depuis_visite' => 0,
                'is_dormant' => false,
            ];
            if (! empty($data['statut'])) {
                $updates['statut'] = $data['statut'];
            }
            $prospect->update($updates);

            return $visite;
        });

        $prospect->load('visites');

        return response()->json([
            'success' => true,
            'data' => [
                'visite' => $visite,
                'prospect' => $prospect,
            ],
        ], 201);
    }

    public function convertir(Request $request, int $prospectId): JsonResponse
    {
        $prospect = CommercialProspect::with('visites')->findOrFail($prospectId);

        $data = $request->validate([
            'client_converti_id' => 'nullable|string|max:60',
            'commentaire' => 'nullable|string',
        ]);

        $clientId = $data['client_converti_id']
            ?? $prospect->client_converti_id
            ?? $this->genererClientConvertiId($prospect);

        $updates = [
            'statut' => 'CONVERTI',
            'client_converti_id' => $clientId,
        ];
        if (array_key_exists('commentaire', $data) && $data['commentaire'] !== null) {
            $updates['commentaire'] = $data['commentaire'];
        }

        $prospect->update($updates);
        $prospect->load('visites');

        return response()->json([
            'success' => true,
            'data' => $prospect,
        ]);
    }

    private function genererCodeProspect(string $antenne): string
    {
        $prefix = 'PROS_'.strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $antenne) ?: 'XX');
        $last = CommercialProspect::query()
            ->where('code', 'like', $prefix.'_%')
            ->orderByDesc('id')
            ->value('code');

        $seq = 1;
        if ($last && preg_match('/_(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.'_'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    private function genererCodeVisite(): string
    {
        $year = now()->format('Y');
        $count = CommercialVisite::query()
            ->where('code_visite', 'like', 'VIS-'.$year.'-%')
            ->count() + 1;

        return 'VIS-'.$year.'-'.str_pad((string) $count, 3, '0', STR_PAD_LEFT);
    }

    private function genererClientConvertiId(CommercialProspect $prospect): string
    {
        $antenne = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $prospect->antenne_code ?? $prospect->province_code ?? 'XX') ?: 'XX');
        $seq = CommercialProspect::query()->where('statut', 'CONVERTI')->count() + 1;

        return 'CLIENT-'.$antenne.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
