<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommandeTerritoriale;
use App\Models\ProduitPharmaceutique;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FluxApprovisionnementController extends Controller
{
    private const ROLES_COMMANDE = ['DEMANDEUR_PHARMACIEN', 'DEMANDEUR_STRUCTURE', 'SUPER_ADMIN', 'CHEF_ANTENNE_PROVINCIALE', 'PHARMACIEN_CHEF_ANTENNE'];
    private const ROLES_ANTENNE = ['CHEF_ANTENNE_PROVINCIALE', 'PHARMACIEN_CHEF_ANTENNE', 'SUPER_ADMIN'];
    private const ROLES_SIEGE = ['APPRO_NATIONAL', 'COLLABORATEUR_APPRO', 'DG', 'DIR', 'DAF', 'ACHATS', 'PHARMACIEN_CHEF_NATIONAL', 'SUPER_ADMIN'];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $stage = $this->stage($user->code_role);

        $query = CommandeTerritoriale::with(['antenne', 'lignes'])->orderByDesc('id');

        if ($stage === 'commande') {
            $query->where('demandeur_user_id', $user->id);
        } elseif ($stage === 'antenne' && $user->province_id) {
            $query->where('antenne_id', $user->province_id);
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->query('statut'));
        }

        return response()->json([
            'success' => true,
            'stage' => $stage,
            'commandes' => $query->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->code_role, self::ROLES_COMMANDE, true)) {
            return response()->json(['success' => false, 'message' => 'Seul un partenaire ou une antenne peut enregistrer une commande.'], 403);
        }

        $data = $request->validate([
            'antenne_id' => 'required|exists:antennes_provinciales,id',
            'partenaire_nom' => 'required|string|max:200',
            'partenaire_type' => 'nullable|string|max:40',
            'motif' => 'nullable|string|max:1000',
            'lignes' => 'required|array|min:1',
            'lignes.*.produit_id' => 'nullable|exists:produits_pharmaceutiques,id',
            'lignes.*.designation' => 'required|string|max:200',
            'lignes.*.quantite_demandee' => 'required|integer|min:1',
        ]);

        if (in_array($user->code_role, ['CHEF_ANTENNE_PROVINCIALE', 'PHARMACIEN_CHEF_ANTENNE'], true)
            && (int) $user->province_id !== (int) $data['antenne_id']) {
            return response()->json(['success' => false, 'message' => 'Cette commande doit concerner votre antenne.'], 403);
        }

        $commande = DB::transaction(function () use ($data, $user) {
            $montant = 0;
            $lignes = [];
            foreach ($data['lignes'] as $ligne) {
                $prix = 0;
                if (!empty($ligne['produit_id'])) {
                    $prix = (float) (ProduitPharmaceutique::find($ligne['produit_id'])->prix_unitaire_xaf ?? 0);
                }
                $montant += $prix * (int) $ligne['quantite_demandee'];
                $lignes[] = [
                    'produit_id' => $ligne['produit_id'] ?? null,
                    'designation' => $ligne['designation'],
                    'quantite_demandee' => (int) $ligne['quantite_demandee'],
                    'quantite_dispersee' => 0,
                    'prix_unitaire_xaf' => $prix,
                ];
            }

            $commande = CommandeTerritoriale::create([
                'numero_commande' => 'CMD-'.now()->format('Ymd').'-'.str_pad((string) (CommandeTerritoriale::count() + 1), 4, '0', STR_PAD_LEFT),
                'antenne_id' => $data['antenne_id'],
                'demandeur_user_id' => $user->id,
                'demandeur_nom' => $user->name,
                'demandeur_role' => $user->role,
                'partenaire_nom' => $data['partenaire_nom'],
                'partenaire_type' => $data['partenaire_type'] ?? 'PHARMACIE',
                'statut' => 'SOUMISE_ANTENNE',
                'motif' => $data['motif'] ?? null,
                'montant_total_xaf' => $montant,
            ]);
            foreach ($lignes as $ligne) {
                $commande->lignes()->create($ligne);
            }

            return $commande->load(['antenne', 'lignes']);
        });

        return response()->json(['success' => true, 'commande' => $commande], 201);
    }

    public function transmettre(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->code_role, self::ROLES_ANTENNE, true)) {
            return response()->json(['success' => false, 'message' => 'Seule l\'antenne peut transmettre la commande au siège.'], 403);
        }

        $commande = CommandeTerritoriale::findOrFail($id);
        if ($user->code_role !== 'SUPER_ADMIN' && (int) $commande->antenne_id !== (int) $user->province_id) {
            return response()->json(['success' => false, 'message' => 'Cette commande n\'appartient pas à votre antenne.'], 403);
        }
        if ($commande->statut !== 'SOUMISE_ANTENNE') {
            return response()->json(['success' => false, 'message' => 'Seule une commande reçue par l\'antenne peut être transmise.'], 422);
        }

        $commande->update([
            'statut' => 'TRANSMISE_SIEGE',
            'transmise_at' => now(),
            'transmise_par_nom' => $user->name,
        ]);

        return response()->json(['success' => true, 'commande' => $commande->load(['antenne', 'lignes'])]);
    }

    public function disperser(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->code_role, self::ROLES_SIEGE, true)) {
            return response()->json(['success' => false, 'message' => 'Seul le siège peut disperser une commande.'], 403);
        }

        $data = $request->validate([
            'note_dispersion' => 'nullable|string|max:1000',
            'lignes' => 'required|array|min:1',
            'lignes.*.id' => 'required|integer',
            'lignes.*.quantite_dispersee' => 'required|integer|min:0',
        ]);

        $commande = CommandeTerritoriale::with('lignes')->findOrFail($id);
        if ($commande->statut !== 'TRANSMISE_SIEGE') {
            return response()->json(['success' => false, 'message' => 'La commande doit d\'abord être transmise par l\'antenne.'], 422);
        }

        DB::transaction(function () use ($commande, $data, $user) {
            foreach ($data['lignes'] as $ligne) {
                $row = $commande->lignes->firstWhere('id', (int) $ligne['id']);
                if (!$row) {
                    continue;
                }
                $qte = min((int) $ligne['quantite_dispersee'], (int) $row->quantite_demandee);
                $row->update(['quantite_dispersee' => $qte]);
            }
            $commande->update([
                'statut' => 'DISPERSEE',
                'dispersee_at' => now(),
                'dispersee_par_nom' => $user->name,
                'note_dispersion' => $data['note_dispersion'] ?? null,
            ]);
        });

        return response()->json(['success' => true, 'commande' => $commande->fresh(['antenne', 'lignes'])]);
    }

    public function rejeter(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['motif' => 'required|string|max:500']);
        $commande = CommandeTerritoriale::findOrFail($id);
        $stage = $this->stage($user->code_role);

        if ($stage === 'antenne' && $commande->statut === 'SOUMISE_ANTENNE') {
            $statut = 'REJETEE_ANTENNE';
        } elseif (in_array($stage, ['siege', 'supervision'], true) && $commande->statut === 'TRANSMISE_SIEGE') {
            $statut = 'REJETEE_SIEGE';
        } else {
            return response()->json(['success' => false, 'message' => 'Cette commande ne peut pas être rejetée à cette étape.'], 422);
        }

        $commande->update(['statut' => $statut, 'motif' => $data['motif']]);

        return response()->json(['success' => true, 'commande' => $commande->load(['antenne', 'lignes'])]);
    }

    private function stage(?string $codeRole): string
    {
        if ($codeRole === 'SUPER_ADMIN') {
            return 'supervision';
        }
        if (in_array($codeRole, ['DEMANDEUR_PHARMACIEN', 'DEMANDEUR_STRUCTURE'], true)) {
            return 'commande';
        }
        if (in_array($codeRole, ['CHEF_ANTENNE_PROVINCIALE', 'PHARMACIEN_CHEF_ANTENNE'], true)) {
            return 'antenne';
        }
        if (in_array($codeRole, ['APPRO_NATIONAL', 'COLLABORATEUR_APPRO', 'DG', 'DIR', 'DAF', 'ACHATS', 'PHARMACIEN_CHEF_NATIONAL'], true)) {
            return 'siege';
        }

        return 'suivi';
    }
}
