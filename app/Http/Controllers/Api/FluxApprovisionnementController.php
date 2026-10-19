<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AntenneProvinciale;
use App\Models\CommandeTerritoriale;
use App\Models\FluxPriseEnChargeSiege;
use App\Models\FluxTransfertSiege;
use App\Models\ProduitPharmaceutique;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FluxApprovisionnementController extends Controller
{
    private const ROLES_COMMANDE = ['DEMANDEUR_PHARMACIEN', 'DEMANDEUR_STRUCTURE', 'SUPER_ADMIN', 'ADMIN_METIER', 'CHEF_ANTENNE_PROVINCIALE', 'PHARMACIEN_CHEF_ANTENNE'];
    private const ROLES_ANTENNE = ['CHEF_ANTENNE_PROVINCIALE', 'PHARMACIEN_CHEF_ANTENNE', 'SUPER_ADMIN', 'ADMIN_METIER'];
    private const ROLES_SIEGE = ['APPRO_NATIONAL', 'COLLABORATEUR_APPRO', 'DG', 'DIR', 'DAF', 'ACHATS', 'PHARMACIEN_CHEF_NATIONAL', 'SUPER_ADMIN', 'ADMIN_METIER'];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing(['profil:id,code', 'structure:id,nom']);
        $stage = $this->voitToutesLesDemandes($user) ? 'supervision' : $this->stage($user->code_role);
        $antenneCompte = $this->antenneDuCompte($user);

        $query = CommandeTerritoriale::with(['antenne', 'lignes', 'journal'])->orderByDesc('id');

        /* Seuls le demandeur et l'antenne sont limités. L'admin métier voit tout. */
        if (!$this->voitToutesLesDemandes($user) && $stage === 'commande') {
            $query->where('demandeur_user_id', $user->id);
        } elseif (!$this->voitToutesLesDemandes($user) && $stage === 'antenne') {
            $antenneId = $this->antenneIdUtilisateur($user);
            if ($antenneId) {
                $query->where('antenne_id', $antenneId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->query('statut'));
        }

        $commandes = $query->get()->map(fn ($c) => $this->enrichirCommande($c));

        $payload = [
            'success' => true,
            'stage' => $stage,
            'server_time' => now()->toIso8601String(),
            'compteurs' => $this->compteurs($commandes, $stage),
            'peut_supprimer' => $this->peutSupprimer($user),
            'antenne_compte' => $antenneCompte ? [
                'id' => $antenneCompte->id,
                'nom' => $antenneCompte->nom,
                'chef_lieu' => $antenneCompte->chef_lieu,
            ] : null,
            'structure_compte' => $user->structure?->nom ?? $user->entite,
            'commandes' => $commandes,
        ];

        // Siège (Direction générale) et supervision (admin métier, super admin) :
        // année → mois → antenne qui a transféré → demandeur, ou l'antenne elle-même.
        if (in_array($stage, ['siege', 'supervision'], true)) {
            $payload['groupees'] = $this->grouperPourSiege($commandes);
        }

        return response()->json($payload);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->code_role, self::ROLES_COMMANDE, true)) {
            return response()->json(['success' => false, 'message' => 'Seul un partenaire ou une antenne peut enregistrer une commande.'], 403);
        }

        $data = $request->validate([
            'antenne_id' => 'nullable|exists:antennes_provinciales,id',
            'partenaire_nom' => 'required|string|max:200',
            'partenaire_type' => 'nullable|string|max:40',
            'motif' => 'nullable|string|max:1000',
            'lignes' => 'required|array|min:1',
            'lignes.*.produit_id' => 'nullable|exists:produits_pharmaceutiques,id',
            'lignes.*.designation' => 'required|string|max:200',
            'lignes.*.quantite_demandee' => 'required|integer|min:1',
        ]);

        /* L'antenne de la commande est celle du compte, pas celle envoyée par le formulaire. */
        $antenneCompte = $this->antenneIdUtilisateur($user);
        if (!in_array($user->code_role, ['SUPER_ADMIN', 'ADMIN_METIER'], true)) {
            if (!$antenneCompte) {
                return response()->json(['success' => false, 'message' => 'Aucune antenne n\'est rattachée à ce compte.'], 422);
            }
            $data['antenne_id'] = $antenneCompte;
        } elseif (empty($data['antenne_id'])) {
            $data['antenne_id'] = $antenneCompte;
        }
        if (empty($data['antenne_id'])) {
            return response()->json(['success' => false, 'message' => 'Choisissez une antenne destinataire.'], 422);
        }

        if (in_array($user->code_role, ['CHEF_ANTENNE_PROVINCIALE', 'PHARMACIEN_CHEF_ANTENNE'], true)
            && (int) $antenneCompte !== (int) $data['antenne_id']) {
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
                $qte = (int) $ligne['quantite_demandee'];
                $lignes[] = [
                    'produit_id' => $ligne['produit_id'] ?? null,
                    'designation' => $ligne['designation'],
                    'quantite_demandee' => $qte,
                    'quantite_prise_en_charge' => 0,
                    'quantite_reliquat' => $qte,
                    'quantite_dispersee' => 0,
                    'prix_unitaire_xaf' => $prix,
                ];
            }

            $creeeParChef = in_array($user->code_role, ['CHEF_ANTENNE_PROVINCIALE', 'PHARMACIEN_CHEF_ANTENNE'], true);
            $statutInitial = $creeeParChef ? 'EFFECTUEE_CHEF_ANTENNE' : 'SOUMISE_ANTENNE';
            // Demande du chef : toujours à son nom (pas une pharmacie tierce).
            $partenaireNom = $creeeParChef ? $user->name : $data['partenaire_nom'];
            $partenaireType = $creeeParChef ? 'ANTENNE' : ($data['partenaire_type'] ?? 'PHARMACIE');

            $commande = CommandeTerritoriale::create([
                'numero_commande' => 'CMD-'.now()->format('Ymd').'-'.str_pad((string) (CommandeTerritoriale::count() + 1), 4, '0', STR_PAD_LEFT),
                'antenne_id' => $data['antenne_id'],
                'demandeur_user_id' => $user->id,
                'demandeur_nom' => $user->name,
                'demandeur_role' => $user->role,
                'partenaire_nom' => $partenaireNom,
                'partenaire_type' => $partenaireType,
                'statut' => $statutInitial,
                'motif' => $data['motif'] ?? null,
                'montant_total_xaf' => $montant,
            ]);
            foreach ($lignes as $ligne) {
                $commande->lignes()->create($ligne);
            }

            $commande->load('antenne');
            $detailCreation = $creeeParChef
                ? 'Demande effectuée par le chef d\'antenne '.$user->name
                : 'Commande de '.$user->name.' pour l\'antenne '.($commande->antenne->nom ?? $commande->antenne_id);
            $this->journaliser($commande, $user, 'CREATION', $detailCreation);

            return $commande->load(['antenne', 'lignes', 'journal']);
        });

        return response()->json(['success' => true, 'commande' => $commande], 201);
    }

    /**
     * Transfert antenne → siège (sans prise en charge de quantités).
     * Le chef d'antenne envoie la demande telle quelle au siège.
     */
    public function transferer(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->code_role, self::ROLES_ANTENNE, true)) {
            return response()->json(['success' => false, 'message' => 'Seul le chef d\'antenne peut transférer une commande au siège.'], 403);
        }

        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $commande = CommandeTerritoriale::with(['lignes', 'antenne'])->findOrFail($id);
        $antenneUser = $this->antenneIdUtilisateur($user);
        if (!in_array($user->code_role, ['SUPER_ADMIN', 'ADMIN_METIER'], true) && (int) $commande->antenne_id !== (int) $antenneUser) {
            return response()->json(['success' => false, 'message' => 'Cette commande n\'appartient pas à votre antenne.'], 403);
        }
        if (!in_array($commande->statut, ['SOUMISE_ANTENNE', 'EFFECTUEE_CHEF_ANTENNE'], true)) {
            return response()->json(['success' => false, 'message' => 'Seules les commandes reçues à l\'antenne peuvent être transférées au siège.'], 422);
        }

        $now = now();
        $qteTotale = $commande->lignes->sum(fn ($l) => (int) $l->quantite_demandee);
        $antenneNom = $commande->antenne?->nom ?? ('Antenne #'.$commande->antenne_id);

        DB::transaction(function () use ($commande, $user, $data, $now, $qteTotale, $antenneNom) {
            $commande->update([
                'statut' => 'TRANSFEREE_SIEGE',
                'transferee_au_siege_at' => $now,
                'transferee_au_siege_par_user_id' => $user->id,
                'transferee_au_siege_par_nom' => $user->name,
                'transmise_at' => $commande->transmise_at ?: $now,
                'transmise_par_nom' => $user->name,
            ]);

            FluxTransfertSiege::create([
                'commande_id' => $commande->id,
                'antenne_id' => $commande->antenne_id,
                'user_id' => $user->id,
                'auteur_nom' => $user->name,
                'auteur_role' => $user->role,
                'antenne_nom' => $antenneNom,
                'annee' => (int) $now->format('Y'),
                'mois' => (int) $now->format('n'),
                'nb_lignes' => $commande->lignes->count(),
                'quantite_totale' => $qteTotale,
                'transferee_at' => $now,
                'detail' => $data['note'] ?? 'Transfert de la commande au siège OPN',
            ]);

            $detail = $data['note']
                ?? 'Transférée au siège par '.$user->name.' (antenne '.$antenneNom.', '.$qteTotale.' unité(s) demandée(s))';
            $this->journaliser($commande->fresh(), $user, 'TRANSFERT', $detail);
        });

        return response()->json([
            'success' => true,
            'commande' => $this->enrichirCommande($commande->fresh(['antenne', 'lignes', 'journal'])),
        ]);
    }

    /**
     * Prise en charge siège : selon disponibilité (tout ou partie des quantités).
     * Le reliquat reste en attente d'un prochain arrivage.
     */
    public function transmettre(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->code_role, self::ROLES_SIEGE, true)) {
            return response()->json(['success' => false, 'message' => 'Seul le siège peut prendre en charge une commande selon la disponibilité.'], 403);
        }

        $data = $request->validate([
            'lignes' => 'nullable|array',
            'lignes.*.id' => 'required_with:lignes|integer',
            'lignes.*.quantite' => 'required_with:lignes|integer|min:0',
            'tout' => 'nullable|boolean',
            'note' => 'nullable|string|max:1000',
        ]);

        $commande = CommandeTerritoriale::with('lignes')->findOrFail($id);
        if (!in_array($commande->statut, ['TRANSFEREE_SIEGE', 'TRANSMISE_SIEGE', 'PARTIELLEMENT_TRANSMISE', 'PARTIELLEMENT_ARCHIVEE', 'PARTIELLEMENT_DISPERSEE'], true)) {
            return response()->json(['success' => false, 'message' => 'La commande doit d\'abord être transférée par l\'antenne.'], 422);
        }

        $selections = collect($data['lignes'] ?? []);
        $prendreTout = (bool) ($data['tout'] ?? false) || $selections->isEmpty();

        $plan = [];
        foreach ($commande->lignes as $row) {
            $reliquat = max(0, (int) $row->quantite_demandee - (int) $row->quantite_prise_en_charge);
            if ($reliquat <= 0) {
                continue;
            }
            if ($prendreTout) {
                $qte = $reliquat;
            } else {
                $choix = $selections->firstWhere('id', $row->id);
                $qte = $choix ? min($reliquat, max(0, (int) $choix['quantite'])) : 0;
            }
            if ($qte > 0) {
                $plan[] = ['row' => $row, 'qte' => $qte];
            }
        }

        if ($plan === []) {
            return response()->json(['success' => false, 'message' => 'Indiquez au moins une quantité à prendre en charge.'], 422);
        }

        $totalPris = array_sum(array_column($plan, 'qte'));

        DB::transaction(function () use ($commande, $plan, $user, $data, $totalPris) {
            foreach ($plan as $item) {
                $row = $item['row'];
                $prise = (int) $row->quantite_prise_en_charge + $item['qte'];
                $row->update([
                    'quantite_prise_en_charge' => $prise,
                    'quantite_reliquat' => max(0, (int) $row->quantite_demandee - $prise),
                ]);
            }

            $commande->refresh()->load('lignes');
            $statut = $this->calculerStatut($commande);
            $archive = $commande->lignes->sum(fn ($l) => max(0, (int) $l->quantite_demandee - (int) $l->quantite_prise_en_charge));
            $commande->update([
                'statut' => $statut,
                'prise_en_charge_siege_at' => $commande->prise_en_charge_siege_at ?: now(),
                'prise_en_charge_siege_par_nom' => $user->name,
                'transmise_at' => $commande->transmise_at ?: now(),
                'transmise_par_nom' => $user->name,
            ]);

            $detail = $data['note']
                ?? ($archive > 0
                    ? "Prise en charge siège : {$totalPris} unité(s). {$archive} unité(s) en reliquat."
                    : "Prise en charge siège complète ({$totalPris} unité(s)).");

            FluxPriseEnChargeSiege::create([
                'commande_id' => $commande->id,
                'antenne_id' => $commande->antenne_id,
                'user_id' => $user->id,
                'auteur_nom' => $user->name,
                'auteur_role' => $user->role,
                'quantite_prise' => $totalPris,
                'quantite_reliquat' => $archive,
                'statut_apres' => $statut,
                'detail' => $detail,
                'prise_at' => now(),
            ]);

            $this->journaliser($commande->fresh(), $user, 'PRISE_EN_CHARGE', $detail);
        });

        return response()->json([
            'success' => true,
            'commande' => $this->enrichirCommande($commande->fresh(['antenne', 'lignes', 'journal'])),
        ]);
    }

    /**
     * Dispersion siège : tout ou une partie des quantités déjà prises en charge.
     * Le solde reste ouvert pour une dispersion ultérieure.
     */
    public function disperser(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->code_role, self::ROLES_SIEGE, true)) {
            return response()->json(['success' => false, 'message' => 'Seul le siège peut disperser une commande.'], 403);
        }

        $data = $request->validate([
            'note_dispersion' => 'nullable|string|max:1000',
            'lignes' => 'nullable|array',
            'lignes.*.id' => 'required_with:lignes|integer',
            'lignes.*.quantite' => 'nullable|integer|min:0',
            'lignes.*.quantite_dispersee' => 'nullable|integer|min:0',
            'tout' => 'nullable|boolean',
        ]);

        $commande = CommandeTerritoriale::with('lignes')->findOrFail($id);
        if (!in_array($commande->statut, ['TRANSMISE_SIEGE', 'PARTIELLEMENT_TRANSMISE', 'PARTIELLEMENT_DISPERSEE', 'PARTIELLEMENT_ARCHIVEE'], true)) {
            return response()->json(['success' => false, 'message' => 'La commande doit d\'abord être prise en charge par le siège.'], 422);
        }

        $selections = collect($data['lignes'] ?? []);
        $disperserTout = (bool) ($data['tout'] ?? false) || $selections->isEmpty();

        $plan = [];
        foreach ($commande->lignes as $row) {
            $reste = max(0, (int) $row->quantite_prise_en_charge - (int) $row->quantite_dispersee);
            if ($reste <= 0) {
                continue;
            }
            if ($disperserTout) {
                $qte = $reste;
            } else {
                $choix = $selections->firstWhere('id', $row->id);
                if (!$choix) {
                    continue;
                }
                // `quantite` = vague actuelle ; `quantite_dispersee` = compat ancien front (cumul voulu).
                if (array_key_exists('quantite', $choix) && $choix['quantite'] !== null) {
                    $qte = min($reste, max(0, (int) $choix['quantite']));
                } else {
                    $cible = min((int) $row->quantite_prise_en_charge, max((int) $row->quantite_dispersee, (int) ($choix['quantite_dispersee'] ?? 0)));
                    $qte = $cible - (int) $row->quantite_dispersee;
                }
            }
            if ($qte > 0) {
                $plan[] = ['row' => $row, 'qte' => $qte];
            }
        }

        if ($plan === []) {
            return response()->json(['success' => false, 'message' => 'Indiquez au moins une quantité à disperser.'], 422);
        }

        $totalDisperse = array_sum(array_column($plan, 'qte'));

        DB::transaction(function () use ($commande, $data, $user, $plan, $totalDisperse) {
            foreach ($plan as $item) {
                $row = $item['row'];
                $row->update(['quantite_dispersee' => (int) $row->quantite_dispersee + $item['qte']]);
            }

            $commande->refresh()->load('lignes');
            $statut = $this->calculerStatut($commande);
            $commande->update([
                'statut' => $statut,
                'dispersee_at' => now(),
                'dispersee_par_nom' => $user->name,
                'note_dispersion' => $data['note_dispersion'] ?? $commande->note_dispersion,
            ]);

            $detail = $data['note_dispersion']
                ?? ($statut === 'DISPERSEE'
                    ? 'Dispersion complète'
                    : "Dispersion partielle ({$totalDisperse} unité(s)) — solde pour arrivage ultérieur");
            $this->journaliser($commande->fresh(), $user, 'DISPERSION', $detail);
        });

        return response()->json(['success' => true, 'commande' => $commande->fresh(['antenne', 'lignes', 'journal'])]);
    }

    public function rejeter(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['motif' => 'required|string|max:500']);
        $commande = CommandeTerritoriale::findOrFail($id);
        $stage = $this->stage($user->code_role);

        if ($stage === 'antenne' && in_array($commande->statut, ['SOUMISE_ANTENNE', 'EFFECTUEE_CHEF_ANTENNE'], true)) {
            $statut = 'REJETEE_ANTENNE';
        } elseif (in_array($stage, ['siege', 'supervision'], true) && in_array($commande->statut, ['TRANSFEREE_SIEGE', 'TRANSMISE_SIEGE', 'PARTIELLEMENT_DISPERSEE', 'PARTIELLEMENT_TRANSMISE', 'PARTIELLEMENT_ARCHIVEE'], true)) {
            $statut = 'REJETEE_SIEGE';
        } else {
            return response()->json(['success' => false, 'message' => 'Cette commande ne peut pas être rejetée à cette étape.'], 422);
        }

        $commande->update(['statut' => $statut]);
        $this->journaliser($commande, $user, 'REJET', $data['motif']);

        return response()->json(['success' => true, 'commande' => $commande->load(['antenne', 'lignes', 'journal'])]);
    }

    /** Déduit le statut global à partir des quantités ligne + transfert. */
    private function calculerStatut(CommandeTerritoriale $commande): string
    {
        $lignes = $commande->lignes;
        $reliquat = $lignes->sum(fn ($l) => max(0, (int) $l->quantite_demandee - (int) $l->quantite_prise_en_charge));
        $prise = $lignes->sum(fn ($l) => (int) $l->quantite_prise_en_charge);
        $dispersee = $lignes->sum(fn ($l) => (int) $l->quantite_dispersee);
        $resteDispersion = $lignes->sum(fn ($l) => max(0, (int) $l->quantite_prise_en_charge - (int) $l->quantite_dispersee));
        $dejaTransferee = $commande->transferee_au_siege_at
            || in_array($commande->statut, ['TRANSFEREE_SIEGE', 'TRANSMISE_SIEGE', 'PARTIELLEMENT_ARCHIVEE', 'PARTIELLEMENT_DISPERSEE', 'DISPERSEE'], true);

        if ($prise <= 0) {
            if ($dejaTransferee) {
                return 'TRANSFEREE_SIEGE';
            }

            return $commande->statut === 'EFFECTUEE_CHEF_ANTENNE' ? 'EFFECTUEE_CHEF_ANTENNE' : 'SOUMISE_ANTENNE';
        }
        if ($reliquat > 0) {
            return 'PARTIELLEMENT_ARCHIVEE';
        }
        if ($resteDispersion <= 0 && $dispersee > 0) {
            return 'DISPERSEE';
        }
        if ($dispersee > 0 && $resteDispersion > 0) {
            return 'PARTIELLEMENT_DISPERSEE';
        }

        return 'TRANSMISE_SIEGE';
    }

    /** Compteurs selon le stage (antenne vs siège). */
    private function compteurs($commandes, string $stage): array
    {
        $base = [
            'total' => $commandes->count(),
            'recues' => $commandes->whereIn('statut', ['SOUMISE_ANTENNE', 'EFFECTUEE_CHEF_ANTENNE'])->count(),
            'transferees' => $commandes->whereIn('statut', ['TRANSFEREE_SIEGE', 'TRANSMISE_SIEGE', 'PARTIELLEMENT_ARCHIVEE', 'PARTIELLEMENT_DISPERSEE', 'DISPERSEE'])->count(),
            'en_attente' => $commandes->where('statut', 'TRANSFEREE_SIEGE')->count(),
            'satisfaites' => $commandes->where('statut', 'DISPERSEE')->count(),
            'reliquats' => $commandes->where('statut', 'PARTIELLEMENT_ARCHIVEE')->count(),
            'dispersees' => $commandes->where('statut', 'DISPERSEE')->count(),
            'archivees' => $commandes->where('statut', 'PARTIELLEMENT_ARCHIVEE')->count(),
            'transmises' => $commandes->whereIn('statut', ['TRANSFEREE_SIEGE', 'TRANSMISE_SIEGE', 'PARTIELLEMENT_DISPERSEE', 'PARTIELLEMENT_ARCHIVEE'])->count(),
        ];

        return $base;
    }

    /** Regroupe les commandes du siège : année → mois → antenne → demandeur (ou l'antenne elle-même). */
    private function grouperPourSiege($commandes): array
    {
        $siege = $commandes->filter(fn ($c) => in_array($c->statut, [
            'TRANSFEREE_SIEGE', 'TRANSMISE_SIEGE', 'PARTIELLEMENT_ARCHIVEE',
            'PARTIELLEMENT_TRANSMISE', 'PARTIELLEMENT_DISPERSEE', 'DISPERSEE', 'REJETEE_SIEGE',
        ], true));

        return $siege
            ->groupBy(function ($c) {
                $ref = $c->transferee_au_siege_at ?: $c->created_at;

                return $ref ? (int) $ref->format('Y') : (int) now()->format('Y');
            })
            ->map(function ($cmdsAnnee, $annee) {
                $mois = $cmdsAnnee->groupBy(function ($c) {
                    $ref = $c->transferee_au_siege_at ?: $c->created_at;

                    return $ref ? (int) $ref->format('n') : (int) now()->format('n');
                })->map(function ($cmdsMois, $moisNum) {
                    $antennes = $cmdsMois->groupBy(fn ($c) => (string) ($c->antenne_id ?? 0))
                        ->map(function ($cmdsAntenne, $antenneId) {
                            $premiere = $cmdsAntenne->first();
                            $origines = $cmdsAntenne->groupBy(function ($c) {
                                if (($c->partenaire_type ?? '') === 'ANTENNE') {
                                    return 'antenne';
                                }

                                return 'demandeur:'.($c->demandeur_user_id ?: mb_strtolower((string) $c->demandeur_nom));
                            })->map(function ($cmdsOrigine, $cle) {
                                $premiereOrigine = $cmdsOrigine->first();
                                $parAntenne = $cle === 'antenne';

                                return [
                                    'cle' => (string) $cle,
                                    'type' => $parAntenne ? 'antenne' : 'demandeur',
                                    'nom' => $premiereOrigine->demandeur_nom ?: ($parAntenne ? 'Antenne' : 'Demandeur'),
                                    'role' => $premiereOrigine->demandeur_role,
                                    'partenaire_nom' => $premiereOrigine->partenaire_nom,
                                    'nb_commandes' => $cmdsOrigine->count(),
                                    'commandes' => $cmdsOrigine->values(),
                                ];
                            })->sortBy(fn ($origine) => ($origine['type'] === 'antenne' ? '0' : '1').$origine['nom'])->values();

                            return [
                                'antenne_id' => (int) $antenneId,
                                'antenne_nom' => $premiere->antenne->nom ?? ('Antenne #'.$antenneId),
                                'chef_lieu' => $premiere->antenne->chef_lieu ?? null,
                                'transferee_par' => $cmdsAntenne->pluck('transferee_au_siege_par_nom')->filter()->unique()->values()->all(),
                                'nb_commandes' => $cmdsAntenne->count(),
                                'en_attente' => $cmdsAntenne->where('statut', 'TRANSFEREE_SIEGE')->count(),
                                'reliquats' => $cmdsAntenne->where('statut', 'PARTIELLEMENT_ARCHIVEE')->count(),
                                'satisfaites' => $cmdsAntenne->where('statut', 'DISPERSEE')->count(),
                                'origines' => $origines,
                            ];
                        })
                        ->sortBy('antenne_nom')
                        ->values();

                    return [
                        'mois' => (int) $moisNum,
                        'libelle' => $this->libelleMois((int) $moisNum),
                        'nb_commandes' => $cmdsMois->count(),
                        'antennes' => $antennes,
                    ];
                })->sortByDesc('mois')->values();

                return [
                    'annee' => (int) $annee,
                    'nb_commandes' => $cmdsAnnee->count(),
                    'mois' => $mois,
                ];
            })
            ->sortByDesc('annee')
            ->values()
            ->all();
    }

    private function libelleMois(int $mois): string
    {
        $noms = [1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
            7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'];

        return $noms[$mois] ?? (string) $mois;
    }

    /** Ajoute la traçabilité et les totaux quantités à une commande. */
    private function enrichirCommande(CommandeTerritoriale $commande): CommandeTerritoriale
    {
        $lignes = $commande->lignes ?? collect();
        $demande = $lignes->sum(fn ($l) => (int) $l->quantite_demandee);
        $prise = $lignes->sum(fn ($l) => (int) $l->quantite_prise_en_charge);
        $dispersee = $lignes->sum(fn ($l) => (int) $l->quantite_dispersee);
        $reliquat = max(0, $demande - $prise);

        $commande->setAttribute('tracabilite', [
            'initiee_par' => $commande->demandeur_nom,
            'initiee_role' => $commande->demandeur_role,
            'partenaire' => $commande->partenaire_nom,
            'antenne' => $commande->antenne?->nom,
            'antenne_chef_lieu' => $commande->antenne?->chef_lieu,
            'transferee_par' => $commande->transferee_au_siege_par_nom,
            'transferee_at' => $commande->transferee_au_siege_at,
            'prise_en_charge_par' => $commande->prise_en_charge_siege_par_nom,
            'prise_en_charge_at' => $commande->prise_en_charge_siege_at,
            'dispersee_par' => $commande->dispersee_par_nom,
            'dispersee_at' => $commande->dispersee_at,
        ]);
        $commande->setAttribute('initiee_par_antenne', ($commande->partenaire_type ?? '') === 'ANTENNE');
        $commande->setAttribute('quantites', [
            'demandee' => $demande,
            'prise' => $prise,
            'reliquat' => $reliquat,
            'dispersee' => $dispersee,
        ]);

        return $commande;
    }

    /** Supprime une commande visible par le profil, si la case Supprimer est cochée sur le flux. */
    public function supprimer(Request $request, int $id): JsonResponse
    {
        $user = $request->user()->loadMissing('profil:id,code');
        if (!$this->peutSupprimer($user)) {
            return response()->json(['success' => false, 'message' => 'Ce profil n\'a pas le droit de supprimer une commande.'], 403);
        }

        $commande = CommandeTerritoriale::findOrFail($id);
        if (!$this->commandeVisible($user, $commande)) {
            return response()->json(['success' => false, 'message' => 'Cette commande n\'est pas accessible à ce profil.'], 403);
        }

        $commande->delete();

        return response()->json(['success' => true]);
    }

    private function journaliser(CommandeTerritoriale $commande, $user, string $action, ?string $detail = null): void
    {
        $commande->journal()->create([
            'user_id' => $user->id,
            'antenne_id' => $commande->antenne_id,
            'auteur_nom' => $user->name,
            'auteur_role' => $user->role,
            'action' => $action,
            'statut' => $commande->statut,
            'detail' => $detail,
        ]);
    }

    /** La case Supprimer de l'habilitation flux autorise la suppression des commandes. */
    private function peutSupprimer($user): bool
    {
        $user->loadMissing('profil.habilitations');
        $habilitations = $user->profil?->habilitations;
        if (!$habilitations) {
            return false;
        }

        return $habilitations
            ->whereIn('code', ['flux', 'demandes'])
            ->contains(fn ($habilitation) => (bool) $habilitation->pivot->supprimer);
    }

    /** Même périmètre que la liste : ses commandes, celles de son antenne, ou toutes. */
    private function commandeVisible($user, CommandeTerritoriale $commande): bool
    {
        if ($this->voitToutesLesDemandes($user)) {
            return true;
        }

        $stage = $this->stage($user->code_role);
        if ($stage === 'commande') {
            return (int) $commande->demandeur_user_id === (int) $user->id;
        }
        if ($stage === 'antenne') {
            $antenneId = $this->antenneIdUtilisateur($user);

            return $antenneId && (int) $commande->antenne_id === (int) $antenneId;
        }

        return true;
    }

    /** Antenne rattachée au compte, celle choisie à la création du profil. */
    private function antenneDuCompte($user): ?AntenneProvinciale
    {
        $id = $this->antenneIdUtilisateur($user);

        return $id ? AntenneProvinciale::find($id) : null;
    }

    /** Antenne rattachée au compte (antenne_id prioritaire, sinon province_id historique). */
    private function antenneIdUtilisateur($user): ?int
    {
        $id = $user->antenne_id ?: $user->province_id;

        return $id ? (int) $id : null;
    }

    /** L'admin métier et le super administrateur consultent toutes les demandes, quelle que soit l'antenne. */
    private function voitToutesLesDemandes($user): bool
    {
        return in_array($user->code_role, ['SUPER_ADMIN', 'ADMIN_METIER'], true)
            || in_array($user->profil?->code, ['sa', 'admin_metier', 'dsi'], true);
    }

    private function stage(?string $codeRole): string
    {
        if (in_array($codeRole, ['SUPER_ADMIN', 'ADMIN_METIER'], true)) {
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
