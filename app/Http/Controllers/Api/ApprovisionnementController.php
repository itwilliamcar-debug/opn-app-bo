<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DemandeApprovisionnement;
use App\Models\LigneDemande;
use App\Models\AntenneProvinciale;
use App\Models\StructureSanitaire;
use App\Models\ProduitPharmaceutique;
use App\Models\HistoriqueModificationDemande;
use App\Models\AuditLog;
use App\Models\CommandeFournisseur;
use App\Models\Fournisseur;
use App\Models\LigneCommandeFournisseur;
use App\Services\CMMCalculationService;
use App\Services\FefoAllocationService;
use App\Services\OpnAuthorizationService;
use App\Services\VisaCircuitService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ApprovisionnementController extends Controller
{
    protected CMMCalculationService $cmmService;
    protected OpnAuthorizationService $authService;
    protected VisaCircuitService $visaService;
    protected FefoAllocationService $fefoService;

    public function __construct(
        CMMCalculationService $cmmService,
        OpnAuthorizationService $authService,
        VisaCircuitService $visaService,
        FefoAllocationService $fefoService
    ) {
        $this->cmmService = $cmmService;
        $this->authService = $authService;
        $this->visaService = $visaService;
        $this->fefoService = $fefoService;
    }

    /**
     * Identité audit / signature — toujours issue du token Sanctum (jamais du client).
     *
     * @return array{user: \App\Models\User, nom: string, role: string}
     */
    protected function actorFromRequest(Request $request): array
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Non authentifie. Session Sanctum requise.');
        }

        return [
            'user' => $user,
            'nom' => $user->name,
            'role' => $user->role ?? $user->code_role ?? 'Agent OPN',
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = DemandeApprovisionnement::with(['antenne', 'structure', 'lignes.produit']);

        if ($user) {
            $query = $this->authService->applyScope($query, $user, 'antenne_id');
        } elseif ($request->has('antenne_id')) {
            $query->where('antenne_id', $request->query('antenne_id'));
        }

        if ($request->has('statut') && $request->query('statut') !== 'ALL') {
            $query->where('statut', $request->query('statut'));
        }

        $demandes = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'count' => $demandes->count(),
            'demandes' => $demandes,
        ]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $demande = DemandeApprovisionnement::with(['antenne', 'structure', 'lignes.produit', 'historiqueModifications', 'livraisons.preuve'])
            ->findOrFail($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifie. Session Sanctum requise.',
            ], 401);
        }

        $canViewNational = $this->authService->can(
            $user,
            'demand.view.national',
            $demande->antenne_id,
            $demande->structure_id
        );
        $canViewAntenna = $this->authService->can(
            $user,
            'demand.view.antenna',
            $demande->antenne_id,
            $demande->structure_id
        );
        if (!$canViewNational && !$canViewAntenna) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé : Ce dossier n\'appartient pas à votre périmètre provincial autorisé.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'demande' => $demande,
        ]);
    }

    public function simulateCmm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'antenne_id' => 'required|exists:antennes_provinciales,id',
            'produit_id' => 'required|exists:produits_pharmaceutiques,id',
            'stock_securite_jours' => 'nullable|integer|min:15|max:90',
            'coeff_saisonnier' => 'nullable|numeric|min:1.0|max:2.0',
        ]);

        $antenne = AntenneProvinciale::findOrFail($validated['antenne_id']);
        $produit = ProduitPharmaceutique::findOrFail($validated['produit_id']);

        $cmmLocale = max(500, (int) round($produit->cmm_nationale * ($antenne->stock_valeur_xaf / 800000000)));

        $stockDispo = $produit->lots()
            ->where('antenne_id', $antenne->id)
            ->where('is_quarantine', false)
            ->sum('quantite_disponible');

        $result = $this->cmmService->calculateNeed([
            'cmm_locale' => $cmmLocale,
            'stock_dispo' => $stockDispo,
            'en_transit' => 0,
            'lead_time_jours' => $antenne->lead_time_jours,
            'stock_securite_jours' => $validated['stock_securite_jours'] ?? 45,
            'coeff_saisonnier' => $validated['coeff_saisonnier'] ?? 1.2,
            'prix_unitaire_xaf' => $produit->prix_unitaire_xaf,
        ]);

        return response()->json([
            'success' => true,
            'antenne' => $antenne,
            'produit' => $produit,
            'simulation' => $result,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'antenne_id' => 'required|exists:antennes_provinciales,id',
            'structure_id' => 'nullable|exists:structures_sanitaires,id',
            'demandeur_nom' => 'required|string',
            'motif' => 'nullable|string',
            'lignes' => 'required|array|min:1',
            'lignes.*.produit_id' => 'required|exists:produits_pharmaceutiques,id',
            'lignes.*.quantite_demandee' => 'required|integer|min:1',
        ]);

        $antenne = AntenneProvinciale::findOrFail($validated['antenne_id']);
        $numeroDemande = 'OPN-CMD-2026-' . rand(100, 999);

        $totalDemandee = 0;
        $montantTotalXAF = 0;

        $demande = DemandeApprovisionnement::create([
            'numero_demande' => $numeroDemande,
            'antenne_id' => $antenne->id,
            'structure_id' => $validated['structure_id'] ?? null,
            'demandeur_nom' => $validated['demandeur_nom'],
            'statut' => 'SOUMIS',
            'etape_index' => 1,
            'mode_transport' => $antenne->mode_transport_principal,
            'numero_bordereau' => 'BL-GEN-' . rand(1000, 9999),
            'transporteur' => 'Flotte Logistique OPN Owendo',
            'chauffeur_ou_agent' => 'M. Rodrigue MAVOUNGOU',
            'motif' => $validated['motif'] ?? 'Réassort régulier CMM',
            'historique_workflow' => [
                ['etape' => 'Création demande', 'date' => now()->format('d/m/Y H:i'), 'auteur' => $validated['demandeur_nom'], 'valide' => true],
                ['etape' => 'Validation Chef Antenne', 'date' => 'En attente', 'auteur' => 'Chef Antenne', 'valide' => false],
                ['etape' => 'Transmission Approvisionnement', 'date' => 'En attente', 'auteur' => 'Appro National', 'valide' => false],
            ]
        ]);

        foreach ($validated['lignes'] as $item) {
            $prod = ProduitPharmaceutique::findOrFail($item['produit_id']);
            
            // Stock disponible local
            $dispo = $prod->lots()
                ->where('antenne_id', $antenne->id)
                ->where('is_quarantine', false)
                ->sum('quantite_disponible');

            $totalLigne = $item['quantite_demandee'] * $prod->prix_unitaire_xaf;
            $montantTotalXAF += $totalLigne;
            $totalDemandee += $item['quantite_demandee'];

            LigneDemande::create([
                'demande_id' => $demande->id,
                'produit_id' => $prod->id,
                'quantite_demandee' => $item['quantite_demandee'],
                'quantite_disponible' => $dispo,
                'quantite_validee' => $item['quantite_demandee'],
                'quantite_refusee' => 0,
                'quantite_reliquat' => 0,
                'prix_unitaire_xaf' => $prod->prix_unitaire_xaf,
                'total_ligne_xaf' => $totalLigne,
            ]);
        }

        $demande->update([
            'montant_total_xaf' => $montantTotalXAF,
            'quantite_totale_demandee' => $totalDemandee,
            'quantite_totale_validee' => $totalDemandee,
        ]);

        AuditLog::create([
            'auteur_nom' => $validated['demandeur_nom'],
            'role' => 'DEMANDEUR',
            'action' => 'CREATION_DEMANDE_APPRO',
            'details' => "Création de la demande {$numeroDemande} pour l'antenne {$antenne->nom} (Montant: {$montantTotalXAF} FCFA).",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Demande d\'approvisionnement enregistrée avec succès.',
            'demande' => $demande->load(['antenne', 'structure', 'lignes.produit']),
        ], 201);
    }

    /**
     * Articles 16, 17 & 18 : Validation Partielle (ex: 80/100), Reliquats,
     * Traçabilité Non-Destructive et Signature Électronique
     */
    public function validatePartial(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifie. Session Sanctum requise.',
            ], 401);
        }
        if (!$this->authService->can($user, 'demand.validate.partial')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé : validation antenne réservée au Chef d\'Antenne Provinciale (pas au Super Admin ni à Appro national).',
            ], 403);
        }

        $validated = $request->validate([
            'motif_general' => 'nullable|string',
            'lignes' => 'required|array|min:1',
            'lignes.*.ligne_id' => 'required|exists:lignes_demande,id',
            'lignes.*.quantite_validee' => 'required|integer|min:0',
            'lignes.*.motif' => 'nullable|string',
            'lignes.*.commentaire' => 'nullable|string',
        ]);

        $demande = DemandeApprovisionnement::with('lignes.produit')->findOrFail($id);
        $userNom = $user->name;
        $userRole = $user->role ?? $user->code_role ?? 'Agent OPN';

        $totalValidee = 0;
        $totalReliquat = 0;
        $hasPartial = false;

        foreach ($validated['lignes'] as $item) {
            $ligne = LigneDemande::where('demande_id', $demande->id)->findOrFail($item['ligne_id']);
            $oldValidee = $ligne->quantite_validee;
            $newValidee = $item['quantite_validee'];

            $demandee = $ligne->quantite_demandee;
            $reliquat = max(0, $demandee - $newValidee);
            $refusee = $demandee - $newValidee;

            if ($reliquat > 0) {
                $hasPartial = true;
            }

            // Enregistrement non-destructif de l'historique si modification
            if ($oldValidee !== $newValidee) {
                HistoriqueModificationDemande::create([
                    'demande_id' => $demande->id,
                    'ligne_id' => $ligne->id,
                    'auteur_nom' => $userNom,
                    'role_auteur' => $userRole,
                    'champ_modifie' => 'quantite_validee',
                    'ancienne_valeur' => "{$oldValidee} unités",
                    'nouvelle_valeur' => "{$newValidee} unités (Reliquat: {$reliquat})",
                    'motif' => $item['motif'] ?? ($validated['motif_general'] ?? 'Disponibilité actuelle limitée en stock'),
                    'commentaire' => $item['commentaire'] ?? null,
                    'ip_address' => $request->ip(),
                ]);
            }

            $ligne->update([
                'quantite_validee' => $newValidee,
                'quantite_refusee' => max(0, $refusee),
                'quantite_reliquat' => $reliquat,
                'motif_reliquat' => $item['motif'] ?? 'Stock disponible partiel',
                'commentaire_ligne' => $item['commentaire'] ?? null,
                'total_ligne_xaf' => $newValidee * $ligne->prix_unitaire_xaf,
            ]);

            $totalValidee += $newValidee;
            $totalReliquat += $reliquat;
        }

        $nouveauStatut = ($hasPartial) ? 'PARTIELLEMENT_VALIDEE' : 'SOUMISE_ANTENNE';
        
        // Article 18 : Génération de la Signature Électronique et du QR Code de vérification
        $jetonSignature = 'OPN-VAL-' . hash('sha256', $demande->numero_demande . $userNom . now() . $totalValidee);
        $qrToken = 'QR-VAL-' . strtoupper(substr(md5($jetonSignature), 0, 16));

        $historiqueWorkflow = $demande->historique_workflow ?? [];
        $historiqueWorkflow[] = [
            'etape' => ($hasPartial ? 'Validation Partielle Antenne' : 'Validation Antenne & Envoi Appro'),
            'date' => now()->format('d/m/Y H:i'),
            'auteur' => "{$userNom} ({$userRole})",
            'details' => "Validé : {$totalValidee} / {$demande->quantite_totale_demandee} unités (Reliquat : {$totalReliquat}). Transmis à la file Collaborateurs Appro.",
            'valide' => true,
        ];

        $demande->update([
            'statut' => $nouveauStatut,
            'etape_index' => 2,
            'quantite_totale_validee' => $totalValidee,
            'quantite_totale_reliquat' => $totalReliquat,
            'jeton_signature_officiel' => $jetonSignature,
            'qr_token_verification' => $qrToken,
            'signataire_nom' => $userNom,
            'signataire_role' => $userRole,
            'date_signature_officielle' => now(),
            'historique_workflow' => $historiqueWorkflow,
        ]);

        AuditLog::create([
            'auteur_nom' => $userNom,
            'role' => $userRole,
            'action' => "VALIDATION_ANTENNE_{$nouveauStatut}",
            'details' => "Validation antenne {$nouveauStatut} sur {$demande->numero_demande} ({$totalValidee} validées, {$totalReliquat} reliquat). Envoi file Appro national.",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Demande {$demande->numero_demande} validée à l'antenne ({$nouveauStatut}) et transmise aux Collaborateurs Appro.",
            'demande' => $demande->load(['antenne', 'structure', 'lignes.produit', 'historiqueModifications']),
            'signature' => [
                'jeton_signature' => $jetonSignature,
                'qr_token' => $qrToken,
                'signataire_nom' => $userNom,
                'signataire_role' => $userRole,
                'date_signature' => now()->format('d/m/Y H:i:s'),
            ]
        ]);
    }

    /**
     * Instruction Collaborateur Appro : prise en charge ou clôture d'instruction
     */
    public function instruct(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:start,complete',
            'commentaire' => 'nullable|string',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifie. Session Sanctum requise.',
            ], 401);
        }
        if (!$this->authService->can($user, 'demand.instruct')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé : seuls les Collaborateurs Appro et la Directrice Appro peuvent instruire.',
            ], 403);
        }

        $auteurNom = $user->name;
        $auteurRole = $user->role ?? $user->code_role ?? 'Agent OPN';

        $demande = DemandeApprovisionnement::findOrFail($id);
        $allowedFrom = ['SOUMISE_ANTENNE', 'PARTIELLEMENT_VALIDEE', 'VALIDEE', 'EN_INSTRUCTION_APPRO'];
        if (!in_array($demande->statut, $allowedFrom, true)) {
            return response()->json([
                'success' => false,
                'message' => "Statut incompatible pour instruction ({$demande->statut}).",
            ], 422);
        }

        $nouveauStatut = $validated['action'] === 'complete' ? 'INSTRUITE' : 'EN_INSTRUCTION_APPRO';
        $historique = $demande->historique_workflow ?? [];
        $historique[] = [
            'etape' => $validated['action'] === 'complete' ? 'Instruction Appro terminée' : 'Prise en charge Collaborateur Appro',
            'date' => now()->format('d/m/Y H:i'),
            'auteur' => "{$auteurNom} ({$auteurRole})",
            'details' => $validated['commentaire'] ?? null,
            'valide' => true,
        ];

        $demande->update([
            'statut' => $nouveauStatut,
            'etape_index' => $validated['action'] === 'complete' ? 4 : 3,
            'historique_workflow' => $historique,
        ]);

        AuditLog::create([
            'auteur_nom' => $auteurNom,
            'role' => $auteurRole,
            'action' => $validated['action'] === 'complete' ? 'INSTRUCTION_APPRO_TERMINEE' : 'INSTRUCTION_APPRO_DEBUT',
            'details' => "Demande {$demande->numero_demande} → {$nouveauStatut}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Demande {$demande->numero_demande} : {$nouveauStatut}",
            'demande' => $demande->fresh(['antenne', 'structure', 'lignes.produit']),
        ]);
    }

    /**
     * Validation nationale — Directrice Appro uniquement
     */
    public function validateNational(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'auteur_nom' => 'nullable|string',
            'auteur_role' => 'nullable|string',
            'commentaire' => 'nullable|string',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifie. Session Sanctum requise.',
            ], 401);
        }
        if (!$this->authService->can($user, 'demand.validate.national')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé : seule la Directrice des Approvisionnements Nationaux peut valider nationalement.',
            ], 403);
        }

        $demande = DemandeApprovisionnement::findOrFail($id);
        if (!in_array($demande->statut, ['INSTRUITE', 'EN_INSTRUCTION_APPRO'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Le dossier doit être instruit avant validation nationale.',
            ], 422);
        }

        $circuit = $this->visaService->resolve((float) $demande->montant_total_xaf);
        $nouveauStatut = $circuit['next_statut_after_appro'];

        $historique = $demande->historique_workflow ?? [];
        $historique[] = [
            'etape' => 'Validation Nationale Appro',
            'date' => now()->format('d/m/Y H:i'),
            'auteur' => $user->name . ' (' . ($user->role ?? $user->code_role ?? 'Agent OPN') . ')',
            'details' => ($validated['commentaire'] ?? 'Dotation nationale autorisée') . " | Circuit: {$circuit['label']}",
            'valide' => true,
        ];

        if ($nouveauStatut === 'EN_VISA_DIR') {
            $historique[] = [
                'etape' => 'Ouverture circuit visas',
                'date' => now()->format('d/m/Y H:i'),
                'auteur' => 'Système OPN',
                'details' => "Montant {$demande->montant_total_xaf} XAF → {$circuit['code']} (file DIR)",
                'valide' => true,
            ];
        }

        $demande->update([
            'statut' => $nouveauStatut,
            'etape_index' => $nouveauStatut === 'EN_VISA_DIR' ? 6 : 5,
            'historique_workflow' => $historique,
        ]);

        AuditLog::create([
            'auteur_nom' => $user->name,
            'role' => $user->role ?? $user->code_role ?? 'Agent OPN',
            'action' => 'VALIDATION_NATIONALE_APPRO',
            'details' => "Validation nationale de {$demande->numero_demande} → {$nouveauStatut} ({$circuit['code']})",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $nouveauStatut === 'EN_VISA_DIR'
                ? "Demande {$demande->numero_demande} validée Appro et envoyée au circuit de visas ({$circuit['label']})."
                : "Demande {$demande->numero_demande} validée nationalement (Appro) — sous seuil visas.",
            'circuit_visa' => $circuit,
            'demande' => $demande->fresh(['antenne', 'structure', 'lignes.produit']),
        ]);
    }

    /**
     * Transmission au service Achats après validation Appro / circuit visas.
     * Crée une CommandeFournisseur liée à la demande (+ lignes).
     * Tente aussi une réservation FEFO hub Owendo (best-effort).
     */
    public function transferToAchats(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifie. Session Sanctum requise.',
            ], 401);
        }
        if (!$this->authService->can($user, 'demand.transfer.achats')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé : transmission Achats réservée à la Directrice Appro.',
            ], 403);
        }

        $demande = DemandeApprovisionnement::with(['lignes.produit'])->findOrFail($id);

        if (CommandeFournisseur::where('demande_id', $demande->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Une commande Achats existe déjà pour cette demande.',
            ], 422);
        }

        $circuit = $this->visaService->resolve((float) $demande->montant_total_xaf);

        $allowed = ['VALIDEE_NATIONALE', 'APPROUVEE_CIRCUIT'];
        if ($circuit['requires_dir'] && $demande->statut === 'VALIDEE_NATIONALE') {
            return response()->json([
                'success' => false,
                'message' => 'Ce montant exige le circuit de visas (DIR/DAF/DG) avant transmission Achats. Lancez d\'abord les visas.',
                'circuit_visa' => $circuit,
            ], 422);
        }

        if (!in_array($demande->statut, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Transmission Achats possible uniquement après VALIDEE_NATIONALE (sans visa) ou APPROUVEE_CIRCUIT.',
            ], 422);
        }

        $fournisseur = Fournisseur::query()->orderBy('id')->first();
        if (!$fournisseur) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun fournisseur référencé — impossible de créer le bon de commande.',
            ], 422);
        }

        $fefoResult = null;
        $commande = null;

        DB::transaction(function () use ($demande, $user, $fournisseur, &$commande, &$fefoResult) {
            $historique = $demande->historique_workflow ?? [];
            $historique[] = [
                'etape' => 'Transmission Service Achats',
                'date' => now()->format('d/m/Y H:i'),
                'auteur' => $user->name . ' (' . ($user->role ?? $user->code_role ?? 'Agent OPN') . ')',
                'details' => 'Dossier transmis — génération bon de commande fournisseur',
                'valide' => true,
            ];

            $demande->update([
                'statut' => 'TRANSMISE_ACHATS',
                'etape_index' => 8,
                'historique_workflow' => $historique,
            ]);

            $numero = 'CMD-ACHAT-2026-' . str_pad((string) $demande->id, 4, '0', STR_PAD_LEFT);

            $commande = CommandeFournisseur::create([
                'demande_id' => $demande->id,
                'numero_commande' => $numero,
                'fournisseur_id' => $fournisseur->id,
                'acheteur_id' => $user->id,
                'montant_total_xaf' => $demande->montant_total_xaf,
                'statut' => 'EN_VALIDATION_DIR',
                'date_commande' => now(),
                'date_livraison_prevue' => now()->addDays(35),
                'conditions_paiement' => 'Lettre de Credit Irrevocable (LC/OMS)',
                'notes' => "Generee depuis demande {$demande->numero_demande}",
            ]);

            foreach ($demande->lignes as $ligne) {
                $qty = (int) ($ligne->quantite_validee ?: $ligne->quantite_demandee);
                if ($qty <= 0) {
                    continue;
                }
                LigneCommandeFournisseur::create([
                    'commande_fournisseur_id' => $commande->id,
                    'produit_id' => $ligne->produit_id,
                    'quantite_commandee' => $qty,
                    'prix_unitaire_xaf' => $ligne->prix_unitaire_xaf,
                    'total_ligne_xaf' => $qty * (float) $ligne->prix_unitaire_xaf,
                ]);
            }

            try {
                $fefoResult = $this->fefoService->reserveForDemande($demande->fresh(['lignes.produit']));
            } catch (\Throwable $e) {
                $fefoResult = [
                    'allocations' => [],
                    'manques' => [],
                    'reserved_total' => 0,
                    'warning' => $e->getMessage(),
                ];
            }

            AuditLog::create([
                'auteur_nom' => $user->name,
                'role' => $user->role ?? $user->code_role ?? 'Agent OPN',
                'action' => 'TRANSMISSION_ACHATS',
                'details' => "{$demande->numero_demande} → commande {$commande->numero_commande} (FEFO reserve: {$fefoResult['reserved_total']})",
                'ip_address' => request()->ip(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => "Demande {$demande->numero_demande} transmise aux Achats — BC {$commande->numero_commande} cree.",
            'demande' => $demande->fresh(['antenne', 'structure', 'lignes.produit']),
            'commande' => $commande->load(['fournisseur', 'lignes.produit']),
            'fefo' => $fefoResult,
        ]);
    }

    /**
     * Réservation FEFO manuelle (hub Owendo) pour une demande validée.
     */
    public function allocateFefo(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Non authentifie.'], 401);
        }
        if (!$this->authService->can($user, 'demand.validate.national')
            && !$this->authService->can($user, 'demand.transfer.achats')) {
            return response()->json(['success' => false, 'message' => 'Acces refuse FEFO.'], 403);
        }

        $demande = DemandeApprovisionnement::with(['lignes.produit'])->findOrFail($id);

        try {
            $result = $this->fefoService->reserveForDemande($demande);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        AuditLog::create([
            'auteur_nom' => $user->name,
            'role' => $user->role ?? $user->code_role ?? 'Agent OPN',
            'action' => 'ALLOCATION_FEFO',
            'details' => "{$demande->numero_demande} : {$result['reserved_total']} unites reservees FEFO",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Allocation FEFO effectuee.',
            'fefo' => $result,
        ]);
    }

    /**
     * Confirmation sortie magasin (consommation réservations FEFO).
     */
    public function confirmFefo(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Non authentifie.'], 401);
        }

        $demande = DemandeApprovisionnement::findOrFail($id);
        $consumed = $this->fefoService->confirmForDemande($demande);

        AuditLog::create([
            'auteur_nom' => $user->name,
            'role' => $user->role ?? $user->code_role ?? 'Agent OPN',
            'action' => 'CONFIRMATION_FEFO',
            'details' => "{$demande->numero_demande} : {$consumed} unites consommees FEFO",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Sortie magasin confirmee ({$consumed} unites).",
            'consumed' => $consumed,
        ]);
    }

    /**
     * Lancer manuellement le circuit visas (dossiers VALIDEE_NATIONALE au-dessus du seuil)
     */
    public function launchVisaCircuit(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'auteur_nom' => 'nullable|string',
            'auteur_role' => 'nullable|string',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifie. Session Sanctum requise.',
            ], 401);
        }
        if (!$this->authService->can($user, 'demand.validate.national')) {
            return response()->json([
                'success' => false,
                'message' => 'Seul le Directeur Appro peut lancer le circuit de visas.',
            ], 403);
        }

        $demande = DemandeApprovisionnement::findOrFail($id);
        $circuit = $this->visaService->resolve((float) $demande->montant_total_xaf);

        if (!$circuit['requires_dir']) {
            return response()->json([
                'success' => false,
                'message' => 'Ce dossier est sous le seuil : aucun visa DIR/DAF/DG requis.',
                'circuit_visa' => $circuit,
            ], 422);
        }

        if (!in_array($demande->statut, ['VALIDEE_NATIONALE', 'TRANSMISE_ACHATS'], true)) {
            return response()->json([
                'success' => false,
                'message' => "Statut incompatible ({$demande->statut}) pour ouvrir le circuit visas.",
            ], 422);
        }

        $historique = $demande->historique_workflow ?? [];
        $historique[] = [
            'etape' => 'Ouverture circuit visas',
            'date' => now()->format('d/m/Y H:i'),
            'auteur' => $user->name . ' (' . ($user->role ?? $user->code_role ?? 'Agent OPN') . ')',
            'details' => $circuit['label'],
            'valide' => true,
        ];

        $demande->update([
            'statut' => 'EN_VISA_DIR',
            'etape_index' => 6,
            'historique_workflow' => $historique,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Circuit visas ouvert — file DIR ({$circuit['label']}).",
            'circuit_visa' => $circuit,
            'demande' => $demande->fresh(['antenne', 'structure', 'lignes.produit']),
        ]);
    }

    /**
     * Visa DIR / DAF / DG selon le statut courant et le seuil XAF
     */
    public function submitVisa(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'auteur_nom' => 'nullable|string',
            'auteur_role' => 'nullable|string',
            'decision' => 'required|in:approve,reject',
            'commentaire' => 'nullable|string',
            'motif' => 'nullable|string',
        ]);

        $demande = DemandeApprovisionnement::findOrFail($id);
        $circuit = $this->visaService->resolve((float) $demande->montant_total_xaf);
        $expectedRole = $this->visaService->expectedRoleForStatut($demande->statut);

        if (!$expectedRole) {
            return response()->json([
                'success' => false,
                'message' => "Cette demande n'est pas en attente de visa ({$demande->statut}).",
            ], 422);
        }

        $user = $request->user();
        $permission = match ($expectedRole) {
            'DIR' => 'demand.visa.dir',
            'DAF' => 'demand.visa.daf',
            'DG' => 'demand.visa.dg',
            default => null,
        };

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifie. Session Sanctum requise.',
            ], 401);
        }
        if ($permission && !$this->authService->can($user, $permission)) {
            return response()->json([
                'success' => false,
                'message' => "Accès refusé : visa {$expectedRole} requis pour cette étape.",
            ], 403);
        }

        if ($user->code_role !== $expectedRole) {
            return response()->json([
                'success' => false,
                'message' => "Cette étape attend le profil {$expectedRole}.",
            ], 403);
        }

        $historique = $demande->historique_workflow ?? [];

        if ($validated['decision'] === 'reject') {
            $motif = $validated['motif'] ?? $validated['commentaire'] ?? 'Rejet visa institutionnel';
            $historique[] = [
                'etape' => "Rejet visa {$expectedRole}",
                'date' => now()->format('d/m/Y H:i'),
                'auteur' => $user->name . ' (' . ($user->role ?? $user->code_role ?? 'Agent OPN') . ')',
                'details' => $motif,
                'valide' => false,
            ];

            $demande->update([
                'statut' => 'REJETEE_VISA',
                'historique_workflow' => $historique,
            ]);

            AuditLog::create([
                'auteur_nom' => $user->name,
            'role' => $user->role ?? $user->code_role ?? 'Agent OPN',
                'action' => "REJET_VISA_{$expectedRole}",
                'details' => "{$demande->numero_demande} : {$motif}",
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Visa {$expectedRole} refusé — dossier REJETEE_VISA.",
                'demande' => $demande->fresh(['antenne', 'structure', 'lignes.produit']),
            ]);
        }

        $nouveauStatut = $this->visaService->nextAfterVisa($demande->statut, $circuit);
        $historique[] = [
            'etape' => "Visa {$expectedRole} accordé",
            'date' => now()->format('d/m/Y H:i'),
            'auteur' => $user->name . ' (' . ($user->role ?? $user->code_role ?? 'Agent OPN') . ')',
            'details' => $validated['commentaire'] ?? "Visa {$expectedRole} — conforme",
            'valide' => true,
        ];

        $demande->update([
            'statut' => $nouveauStatut,
            'etape_index' => $nouveauStatut === 'APPROUVEE_CIRCUIT' ? 7 : 6,
            'historique_workflow' => $historique,
        ]);

        AuditLog::create([
            'auteur_nom' => $user->name,
            'role' => $user->role ?? $user->code_role ?? 'Agent OPN',
            'action' => "VISA_{$expectedRole}_ACCORDE",
            'details' => "{$demande->numero_demande} → {$nouveauStatut}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $nouveauStatut === 'APPROUVEE_CIRCUIT'
                ? "Circuit de visas terminé — dossier APPROUVEE_CIRCUIT."
                : "Visa {$expectedRole} accordé — prochain statut {$nouveauStatut}.",
            'circuit_visa' => $circuit,
            'demande' => $demande->fresh(['antenne', 'structure', 'lignes.produit']),
        ]);
    }

    /**
     * Rejet Appro (collaborateur ou directrice) — retour antenne
     */
    public function reject(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'auteur_nom' => 'nullable|string',
            'auteur_role' => 'nullable|string',
            'motif' => 'required|string|min:10',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifie. Session Sanctum requise.',
            ], 401);
        }

        $demande = DemandeApprovisionnement::findOrFail($id);
        $inVisa = in_array($demande->statut, ['EN_VISA_DIR', 'EN_VISA_DAF', 'EN_VISA_DG'], true);

        if ($inVisa) {
            $expected = $this->visaService->expectedRoleForStatut($demande->statut);
            $perm = match ($expected) {
                'DIR' => 'demand.visa.dir',
                'DAF' => 'demand.visa.daf',
                'DG' => 'demand.visa.dg',
                default => 'demand.reject',
            };
            if (!$this->authService->can($user, $perm)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acces refuse : rejet visa non autorise pour ce profil.',
                ], 403);
            }
        } elseif (!$this->authService->can($user, 'demand.reject')) {
            return response()->json([
                'success' => false,
                'message' => 'Acces refuse : rejet Appro non autorise pour ce profil.',
            ], 403);
        }

        $historique = $demande->historique_workflow ?? [];
        $historique[] = [
            'etape' => $inVisa ? 'Rejet visa' : 'Rejet Appro',
            'date' => now()->format('d/m/Y H:i'),
            'auteur' => $user->name . ' (' . ($user->role ?? $user->code_role ?? 'Agent OPN') . ')',
            'details' => $validated['motif'],
            'valide' => false,
        ];

        $this->fefoService->releaseForDemande($demande);

        $demande->update([
            'statut' => $inVisa ? 'REJETEE_VISA' : 'REJETEE',
            'historique_workflow' => $historique,
        ]);

        AuditLog::create([
            'auteur_nom' => $user->name,
            'role' => $user->role ?? $user->code_role ?? 'Agent OPN',
            'action' => $inVisa ? 'REJET_VISA' : 'REJET_APPRO',
            'details' => "{$demande->numero_demande} : {$validated['motif']}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $inVisa ? 'Visa rejete.' : 'Demande rejetee par Appro.',
            'demande' => $demande->fresh(['antenne', 'structure', 'lignes.produit']),
        ]);
    }

}
