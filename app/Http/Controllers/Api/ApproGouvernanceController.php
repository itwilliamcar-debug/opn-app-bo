<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApproBoucleCorrection;
use App\Models\ApproGouvernanceEvenement;
use App\Models\ApproGouvernanceTache;
use App\Models\ApproRevueControle;
use App\Models\AuditLog;
use App\Models\DemandeApprovisionnement;
use App\Models\User;
use App\Services\OpnAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Gouvernance Appro — tâches, contrôles 4 yeux, boucles de correction.
 * Les décisions métier (valider/rejeter demande) restent sur ApprovisionnementController.
 */
class ApproGouvernanceController extends Controller
{
    public function __construct(protected OpnAuthorizationService $authService)
    {
    }

    protected function actor(Request $request): array
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

    protected function denyUnless(User $user, string $permission): void
    {
        if (!$this->authService->can($user, $permission)) {
            abort(403, 'Permission insuffisante pour cette operation de gouvernance.');
        }
    }

    protected function appendWorkflow(DemandeApprovisionnement $demande, string $etape, string $auteur, string $details, bool $valide = true): void
    {
        $historique = $demande->historique_workflow ?? [];
        $historique[] = [
            'etape' => $etape,
            'date' => now()->toIso8601String(),
            'auteur' => $auteur,
            'details' => $details,
            'valide' => $valide,
        ];
        $demande->historique_workflow = $historique;
        $demande->save();
    }

    protected function logEvent(
        int $demandeId,
        ?int $tacheId,
        string $type,
        array $actor,
        string $details,
        ?array $payload = null
    ): void {
        ApproGouvernanceEvenement::create([
            'demande_id' => $demandeId,
            'tache_id' => $tacheId,
            'type_evenement' => $type,
            'acteur_nom' => $actor['nom'],
            'acteur_role' => $actor['role'],
            'acteur_user_id' => $actor['user']->id,
            'details' => $details,
            'payload' => $payload,
        ]);
    }

    protected function nextCode(string $prefix, string $table, string $column): string
    {
        $year = now()->format('Y');
        $count = DB::table($table)->where($column, 'like', "{$prefix}-{$year}-%")->count() + 1;

        return sprintf('%s-%s-%04d', $prefix, $year, $count);
    }

    public function todayQueue(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'governance.view');

        $aControler = ApproGouvernanceTache::with(['demande.antenne', 'assignee'])
            ->where('statut', 'SOUMISE_CONTROLE')
            ->orderByRaw("CASE priorite WHEN 'URGENTE' THEN 0 WHEN 'HAUTE' THEN 1 ELSE 2 END")
            ->orderBy('submitted_at')
            ->limit(50)
            ->get();

        $correctionsAVerifier = ApproBoucleCorrection::with(['demande.antenne', 'tache', 'corrigePar'])
            ->where('statut', 'CORRECTION_SOUMISE')
            ->orderByDesc('corrige_at')
            ->limit(50)
            ->get();

        $mesTaches = ApproGouvernanceTache::with(['demande.antenne'])
            ->where('assignee_user_id', $actor['user']->id)
            ->whereIn('statut', ['A_FAIRE', 'EN_COURS', 'CORRECTION_DEMANDEE', 'EN_ATTENTE'])
            ->orderBy('due_at')
            ->limit(50)
            ->get();

        $mesCorrectionsOuvertes = ApproBoucleCorrection::with(['demande.antenne', 'tache', 'revue'])
            ->where('statut', 'OUVERTE')
            ->whereHas('tache', fn ($q) => $q->where('assignee_user_id', $actor['user']->id))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $enRetard = ApproGouvernanceTache::with(['demande.antenne', 'assignee'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNotIn('statut', ['VALIDEE', 'CLOTUREE', 'ANNULEE'])
            ->orderBy('due_at')
            ->limit(50)
            ->get();

        $charge = User::query()
            ->whereIn('code_role', ['APPRO_NATIONAL', 'COLLABORATEUR_APPRO'])
            ->orderBy('name')
            ->get()
            ->map(function (User $u) {
                $base = ApproGouvernanceTache::where('assignee_user_id', $u->id);
                $ouvertes = (clone $base)->whereNotIn('statut', ['VALIDEE', 'CLOTUREE', 'ANNULEE'])->count();
                $urgentes = (clone $base)->where('priorite', 'URGENTE')
                    ->whereNotIn('statut', ['VALIDEE', 'CLOTUREE', 'ANNULEE'])->count();
                $retard = (clone $base)->whereNotNull('due_at')->where('due_at', '<', now())
                    ->whereNotIn('statut', ['VALIDEE', 'CLOTUREE', 'ANNULEE'])->count();
                $enControle = (clone $base)->where('statut', 'SOUMISE_CONTROLE')->count();
                $terminees = (clone $base)->whereIn('statut', ['VALIDEE', 'CLOTUREE'])->count();

                return [
                    'user_id' => $u->id,
                    'name' => $u->name,
                    'code_role' => $u->code_role,
                    'ouvertes' => $ouvertes,
                    'urgentes' => $urgentes,
                    'en_retard' => $retard,
                    'en_controle' => $enControle,
                    'terminees' => $terminees,
                ];
            });

        return response()->json([
            'success' => true,
            'queue' => [
                'a_controler' => $aControler,
                'corrections_a_verifier' => $correctionsAVerifier,
                'mes_taches' => $mesTaches,
                'mes_corrections_ouvertes' => $mesCorrectionsOuvertes,
                'en_retard' => $enRetard,
                'charge_collaborateurs' => $charge,
            ],
        ]);
    }

    public function listTasks(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'governance.view');

        $query = ApproGouvernanceTache::with(['demande.antenne', 'assignee', 'createdBy'])
            ->orderByDesc('updated_at');

        if ($request->filled('statut') && $request->query('statut') !== 'ALL') {
            $query->where('statut', $request->query('statut'));
        }
        if ($request->filled('demande_id')) {
            $query->where('demande_id', (int) $request->query('demande_id'));
        }
        if ($request->filled('assignee_user_id')) {
            $query->where('assignee_user_id', (int) $request->query('assignee_user_id'));
        }
        if ($request->boolean('mine')) {
            $query->where('assignee_user_id', $actor['user']->id);
        }

        $tasks = $query->limit(200)->get();

        return response()->json(['success' => true, 'count' => $tasks->count(), 'taches' => $tasks]);
    }

    public function createTask(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'governance.task.assign');

        $data = $request->validate([
            'demande_id' => ['required', 'integer', 'exists:demandes_approvisionnement,id'],
            'type' => ['nullable', Rule::in(['INSTRUCTION', 'CONTROLE', 'CORRECTION', 'DOCUMENTAIRE', 'CLOTURE_DOSSIER', 'AUTRE'])],
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'instruction' => ['nullable', 'string', 'max:5000'],
            'priorite' => ['nullable', Rule::in(['NORMALE', 'HAUTE', 'URGENTE'])],
            'assignee_user_id' => ['required', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
        ]);

        $demande = DemandeApprovisionnement::findOrFail($data['demande_id']);
        $assignee = User::findOrFail($data['assignee_user_id']);

        if (!in_array($assignee->code_role, ['APPRO_NATIONAL', 'COLLABORATEUR_APPRO'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Le destinataire doit appartenir a la Direction des Approvisionnements.',
            ], 422);
        }

        $tache = DB::transaction(function () use ($data, $demande, $actor) {
            $tache = ApproGouvernanceTache::create([
                'demande_id' => $demande->id,
                'code_tache' => $this->nextCode('TACH', 'appro_gouvernance_taches', 'code_tache'),
                'type' => $data['type'] ?? 'INSTRUCTION',
                'titre' => $data['titre'],
                'description' => $data['description'] ?? null,
                'instruction' => $data['instruction'] ?? null,
                'statut' => 'A_FAIRE',
                'priorite' => $data['priorite'] ?? 'NORMALE',
                'assignee_user_id' => $data['assignee_user_id'],
                'created_by_user_id' => $actor['user']->id,
                'due_at' => $data['due_at'] ?? null,
                'demande_statut_snapshot' => $demande->statut,
            ]);

            $demande->gouvernance_flag = 'SOUS_CONTROLE';
            $demande->save();

            $this->appendWorkflow(
                $demande,
                'GOUV_TACHE_CREEE',
                $actor['nom'],
                "Tache {$tache->code_tache} affectee — {$tache->titre}"
            );

            $this->logEvent($demande->id, $tache->id, 'TACHE_CREEE', $actor, $tache->titre, [
                'assignee_user_id' => $tache->assignee_user_id,
                'priorite' => $tache->priorite,
            ]);

            AuditLog::create([
                'auteur_nom' => $actor['nom'],
                'role' => $actor['role'],
                'action' => 'GOUV_TACHE_CREEE',
                'details' => "{$tache->code_tache} / demande {$demande->numero_demande}",
                'ip_address' => request()->ip(),
            ]);

            return $tache->fresh(['assignee', 'demande.antenne']);
        });

        return response()->json(['success' => true, 'tache' => $tache], 201);
    }

    public function reassignTask(Request $request, int $id): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'governance.task.assign');

        $data = $request->validate([
            'assignee_user_id' => ['required', 'integer', 'exists:users,id'],
            'motif' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $tache = ApproGouvernanceTache::findOrFail($id);
        if (in_array($tache->statut, ['VALIDEE', 'CLOTUREE', 'ANNULEE'], true)) {
            return response()->json(['success' => false, 'message' => 'Tache cloturee : reaffectation interdite.'], 422);
        }

        $ancien = $tache->assignee_user_id;
        $tache->assignee_user_id = $data['assignee_user_id'];
        $tache->save();

        $this->logEvent($tache->demande_id, $tache->id, 'TACHE_REAFFECTEE', $actor, $data['motif'], [
            'ancien_assignee_user_id' => $ancien,
            'nouveau_assignee_user_id' => $data['assignee_user_id'],
        ]);

        AuditLog::create([
            'auteur_nom' => $actor['nom'],
            'role' => $actor['role'],
            'action' => 'GOUV_TACHE_REAFFECTEE',
            'details' => "{$tache->code_tache} : {$data['motif']}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['success' => true, 'tache' => $tache->fresh(['assignee', 'demande'])]);
    }

    public function startTask(Request $request, int $id): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'governance.task.execute');

        $tache = ApproGouvernanceTache::findOrFail($id);
        $this->assertAssigneeOrDirector($tache, $actor['user']);

        if (!in_array($tache->statut, ['A_FAIRE', 'EN_ATTENTE', 'CORRECTION_DEMANDEE'], true)) {
            return response()->json(['success' => false, 'message' => 'Statut incompatible pour prise en charge.'], 422);
        }

        $from = $tache->statut;
        $tache->statut = 'EN_COURS';
        $tache->save();

        $this->logEvent($tache->demande_id, $tache->id, 'TACHE_EN_COURS', $actor, "{$from} → EN_COURS");

        return response()->json(['success' => true, 'tache' => $tache->fresh(['assignee', 'demande'])]);
    }

    public function submitForControl(Request $request, int $id): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'governance.task.submit');

        $data = $request->validate([
            'commentaire' => ['nullable', 'string', 'max:5000'],
        ]);

        $tache = ApproGouvernanceTache::findOrFail($id);
        $this->assertAssigneeOrDirector($tache, $actor['user']);

        if (!in_array($tache->statut, ['EN_COURS', 'CORRECTION_EFFECTUEE', 'A_FAIRE'], true)) {
            return response()->json(['success' => false, 'message' => 'La tache ne peut pas etre soumise dans cet etat.'], 422);
        }

        $tache = DB::transaction(function () use ($tache, $actor, $data) {
            $tache->statut = 'SOUMISE_CONTROLE';
            $tache->submitted_at = now();
            $tache->save();

            $demande = $tache->demande;
            $demande->gouvernance_flag = 'SOUS_CONTROLE';
            $demande->save();

            $this->appendWorkflow(
                $demande,
                'GOUV_SOUMISE_CONTROLE',
                $actor['nom'],
                "{$tache->code_tache} soumise au controle".(!empty($data['commentaire']) ? ' — '.$data['commentaire'] : '')
            );

            $this->logEvent($tache->demande_id, $tache->id, 'SOUMISE_CONTROLE', $actor, $data['commentaire'] ?? 'Soumission pour controle');

            AuditLog::create([
                'auteur_nom' => $actor['nom'],
                'role' => $actor['role'],
                'action' => 'GOUV_SOUMISE_CONTROLE',
                'details' => $tache->code_tache,
                'ip_address' => request()->ip(),
            ]);

            return $tache->fresh(['assignee', 'demande']);
        });

        return response()->json(['success' => true, 'tache' => $tache]);
    }

    public function controlDecision(Request $request, int $id): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'governance.review.decide');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['VALIDE', 'CORRECTION', 'REJETE', 'EN_ATTENTE'])],
            'constat' => ['required', 'string', 'min:5', 'max:5000'],
            'recommandation' => ['nullable', 'string', 'max:5000'],
            'action_attendue' => ['nullable', 'string', 'max:5000'],
            'type_controle' => ['nullable', Rule::in(['COHERENCE_QTY', 'SEUIL_VISA', 'STOCK_FEFO', 'DOCUMENTAIRE', 'AUTRE'])],
            'elements_concernes' => ['nullable', 'array'],
        ]);

        $tache = ApproGouvernanceTache::with('assignee')->findOrFail($id);

        if ($tache->statut !== 'SOUMISE_CONTROLE') {
            return response()->json(['success' => false, 'message' => 'Seules les taches soumises au controle peuvent etre tranchees.'], 422);
        }

        // Principe des quatre yeux : le préparateur ne valide pas son propre travail
        if ($tache->assignee_user_id && (int) $tache->assignee_user_id === (int) $actor['user']->id) {
            return response()->json([
                'success' => false,
                'message' => 'Principe des quatre yeux : vous ne pouvez pas controler une tache que vous avez preparee.',
            ], 422);
        }

        if (in_array($data['decision'], ['CORRECTION', 'REJETE', 'EN_ATTENTE'], true) && empty(trim($data['constat']))) {
            return response()->json(['success' => false, 'message' => 'Motif obligatoire.'], 422);
        }

        $result = DB::transaction(function () use ($tache, $actor, $data) {
            $resultatMap = [
                'VALIDE' => 'CONFORME',
                'CORRECTION' => 'NON_CONFORME',
                'REJETE' => 'NON_CONFORME',
                'EN_ATTENTE' => 'CONFORME_AVEC_RESERVE',
            ];

            $revue = ApproRevueControle::create([
                'demande_id' => $tache->demande_id,
                'tache_id' => $tache->id,
                'numero_revue' => $this->nextCode('REV', 'appro_revues_controle', 'numero_revue'),
                'type_controle' => $data['type_controle'] ?? 'DOCUMENTAIRE',
                'resultat' => $resultatMap[$data['decision']],
                'constat' => $data['constat'],
                'recommandation' => $data['recommandation'] ?? null,
                'controleur_user_id' => $actor['user']->id,
                'controleur_role' => $actor['user']->code_role,
                'reviewed_at' => now(),
                'exige_correction' => $data['decision'] === 'CORRECTION',
                'elements_concernes' => $data['elements_concernes'] ?? null,
            ]);

            $demande = $tache->demande;
            $correction = null;

            if ($data['decision'] === 'VALIDE') {
                $tache->statut = 'VALIDEE';
                $tache->completed_at = now();
                $tache->save();
                $demande->gouvernance_flag = 'RAS';
                $demande->save();

                ApproBoucleCorrection::where('tache_id', $tache->id)
                    ->whereIn('statut', ['OUVERTE', 'CORRECTION_SOUMISE'])
                    ->update([
                        'statut' => 'REVISEE_OK',
                        'valide_par_user_id' => $actor['user']->id,
                        'valide_at' => now(),
                    ]);

                $this->appendWorkflow($demande, 'GOUV_CONTROLE_VALIDE', $actor['nom'], $data['constat']);
            } elseif ($data['decision'] === 'CORRECTION') {
                // Clôturer l'itération précédente soumise comme KO avant d'ouvrir la suivante
                ApproBoucleCorrection::where('tache_id', $tache->id)
                    ->where('statut', 'CORRECTION_SOUMISE')
                    ->update([
                        'statut' => 'REVISEE_KO',
                        'valide_par_user_id' => $actor['user']->id,
                        'valide_at' => now(),
                    ]);

                $tache->statut = 'CORRECTION_DEMANDEE';
                $tache->save();
                $demande->gouvernance_flag = 'CORRECTION_EN_COURS';
                $demande->save();

                $iteration = ApproBoucleCorrection::where('tache_id', $tache->id)->count() + 1;
                $correction = ApproBoucleCorrection::create([
                    'demande_id' => $tache->demande_id,
                    'revue_controle_id' => $revue->id,
                    'tache_id' => $tache->id,
                    'statut' => 'OUVERTE',
                    'motif' => $data['constat'],
                    'action_attendue' => $data['action_attendue'] ?? $data['recommandation'] ?? null,
                    'iteration' => $iteration,
                ]);

                $this->appendWorkflow($demande, 'GOUV_CORRECTION_DEMANDEE', $actor['nom'], $data['constat'], false);
            } elseif ($data['decision'] === 'REJETE') {
                $tache->statut = 'ANNULEE';
                $tache->completed_at = now();
                $tache->save();
                $demande->gouvernance_flag = 'REJETEE_GOUV';
                $demande->save();
                $this->appendWorkflow($demande, 'GOUV_CONTROLE_REJETE', $actor['nom'], $data['constat'], false);
            } else {
                $tache->statut = 'EN_ATTENTE';
                $tache->save();
                $demande->gouvernance_flag = 'EN_ATTENTE_GOUV';
                $demande->save();
                $this->appendWorkflow($demande, 'GOUV_CONTROLE_EN_ATTENTE', $actor['nom'], $data['constat'], false);
            }

            $this->logEvent($tache->demande_id, $tache->id, 'CONTROLE_'.$data['decision'], $actor, $data['constat'], [
                'revue_id' => $revue->id,
                'correction_id' => $correction?->id,
            ]);

            AuditLog::create([
                'auteur_nom' => $actor['nom'],
                'role' => $actor['role'],
                'action' => 'GOUV_CONTROLE_'.$data['decision'],
                'details' => "{$tache->code_tache} / {$revue->numero_revue}",
                'ip_address' => request()->ip(),
            ]);

            return [
                'tache' => $tache->fresh(['assignee', 'demande']),
                'revue' => $revue,
                'correction' => $correction,
            ];
        });

        return response()->json(['success' => true, ...$result]);
    }

    public function submitCorrection(Request $request, int $id): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'governance.correction.submit');

        $data = $request->validate([
            'correction_appliquee' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $correction = ApproBoucleCorrection::with('tache')->findOrFail($id);
        if ($correction->statut !== 'OUVERTE') {
            return response()->json(['success' => false, 'message' => 'Cette boucle de correction n est pas ouverte.'], 422);
        }

        $tache = $correction->tache;
        if ($tache) {
            $this->assertAssigneeOrDirector($tache, $actor['user']);
        }

        $result = DB::transaction(function () use ($correction, $tache, $actor, $data) {
            $correction->statut = 'CORRECTION_SOUMISE';
            $correction->correction_appliquee = $data['correction_appliquee'];
            $correction->corrige_par_user_id = $actor['user']->id;
            $correction->corrige_at = now();
            $correction->save();

            if ($tache) {
                $tache->statut = 'CORRECTION_EFFECTUEE';
                $tache->save();
                // Remettre en file de contrôle
                $tache->statut = 'SOUMISE_CONTROLE';
                $tache->submitted_at = now();
                $tache->save();
            }

            $demande = DemandeApprovisionnement::findOrFail($correction->demande_id);
            $demande->gouvernance_flag = 'SOUS_CONTROLE';
            $demande->save();

            $this->appendWorkflow(
                $demande,
                'GOUV_CORRECTION_SOUMISE',
                $actor['nom'],
                "Iteration {$correction->iteration} — {$data['correction_appliquee']}"
            );

            $this->logEvent($correction->demande_id, $correction->tache_id, 'CORRECTION_SOUMISE', $actor, $data['correction_appliquee'], [
                'correction_id' => $correction->id,
                'iteration' => $correction->iteration,
            ]);

            AuditLog::create([
                'auteur_nom' => $actor['nom'],
                'role' => $actor['role'],
                'action' => 'GOUV_CORRECTION_SOUMISE',
                'details' => "Correction #{$correction->id} iteration {$correction->iteration}",
                'ip_address' => request()->ip(),
            ]);

            return [
                'correction' => $correction->fresh(['corrigePar', 'tache']),
                'tache' => $tache?->fresh(['assignee', 'demande']),
            ];
        });

        return response()->json(['success' => true, ...$result]);
    }

    public function dossierTimeline(Request $request, int $demandeId): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'governance.view');

        $demande = DemandeApprovisionnement::with(['antenne', 'structure'])->findOrFail($demandeId);

        $events = ApproGouvernanceEvenement::where('demande_id', $demandeId)
            ->orderBy('created_at')
            ->get();

        $taches = ApproGouvernanceTache::with(['assignee', 'revues', 'corrections'])
            ->where('demande_id', $demandeId)
            ->orderBy('created_at')
            ->get();

        $workflow = collect($demande->historique_workflow ?? [])->map(fn ($h) => [
            'source' => 'workflow',
            'date' => $h['date'] ?? null,
            'label' => $h['etape'] ?? 'ETAPE',
            'acteur' => $h['auteur'] ?? null,
            'details' => $h['details'] ?? null,
            'valide' => $h['valide'] ?? null,
        ]);

        $gouv = $events->map(fn ($e) => [
            'source' => 'gouvernance',
            'date' => optional($e->created_at)->toIso8601String(),
            'label' => $e->type_evenement,
            'acteur' => $e->acteur_nom,
            'details' => $e->details,
            'valide' => null,
        ]);

        $timeline = $workflow->concat($gouv)->sortBy('date')->values();

        return response()->json([
            'success' => true,
            'demande' => $demande,
            'taches' => $taches,
            'timeline' => $timeline,
            'gouvernance_flag' => $demande->gouvernance_flag,
        ]);
    }

    public function listCollaborators(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'governance.view');

        $users = User::query()
            ->whereIn('code_role', ['APPRO_NATIONAL', 'COLLABORATEUR_APPRO'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'code_role', 'role', 'entite', 'ville']);

        return response()->json(['success' => true, 'collaborateurs' => $users]);
    }

    protected function assertAssigneeOrDirector(ApproGouvernanceTache $tache, User $user): void
    {
        $isAssignee = (int) $tache->assignee_user_id === (int) $user->id;
        $isDirector = $user->code_role === 'APPRO_NATIONAL' && $this->authService->can($user, 'governance.task.assign');

        if (!$isAssignee && !$isDirector) {
            abort(403, 'Cette tache ne vous est pas affectee.');
        }
    }
}
