<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApproSuggestionDg;
use App\Models\ApproSuggestionEvenement;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\OpnAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Circuit Suggestions au DG National.
 * Statuts : BROUILLON → SOUMISE → EN_ETUDE → ACCEPTÉE|REFUSEE|CORRECTION_DEMANDEE → MISE_EN_OEUVRE → CLOTUREE
 */
class ApproSuggestionDgController extends Controller
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
            abort(403, 'Permission suggestion DG insuffisante.');
        }
    }

    protected function logEvent(ApproSuggestionDg $s, array $actor, string $type, string $details, ?array $payload = null): void
    {
        ApproSuggestionEvenement::create([
            'suggestion_id' => $s->id,
            'type_evenement' => $type,
            'acteur_nom' => $actor['nom'],
            'acteur_role' => $actor['role'],
            'acteur_user_id' => $actor['user']->id,
            'details' => $details,
            'payload' => $payload,
        ]);
    }

    protected function nextNumero(): string
    {
        $year = now()->format('Y');
        $count = ApproSuggestionDg::where('numero_suggestion', 'like', "SUG-{$year}-%")->count() + 1;

        return sprintf('SUG-%s-%04d', $year, $count);
    }

    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'suggestion.view');

        $query = ApproSuggestionDg::with(['auteur', 'demande.antenne', 'dg'])
            ->orderByDesc('updated_at');

        $role = $actor['user']->code_role;
        if ($role === 'DG') {
            $query->whereIn('statut', [
                'SOUMISE', 'EN_ETUDE', 'ACCEPTEE', 'REFUSEE', 'CORRECTION_DEMANDEE', 'MISE_EN_OEUVRE', 'CLOTUREE',
            ]);
            if ($request->query('inbox') === '1') {
                $query->whereIn('statut', ['SOUMISE', 'EN_ETUDE']);
            }
        } elseif (in_array($role, ['APPRO_NATIONAL', 'COLLABORATEUR_APPRO'], true)) {
            // Directeur / collab voient leurs suggestions + toutes si APPRO_NATIONAL
            if ($role === 'COLLABORATEUR_APPRO') {
                $query->where('auteur_user_id', $actor['user']->id);
            }
        }

        if ($request->filled('statut') && $request->query('statut') !== 'ALL') {
            $query->where('statut', $request->query('statut'));
        }

        $items = $query->limit(200)->get();

        return response()->json(['success' => true, 'count' => $items->count(), 'suggestions' => $items]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'suggestion.view');

        $s = ApproSuggestionDg::with(['auteur', 'demande.antenne', 'dg', 'evenements', 'documents.versions'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'suggestion' => $s]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'suggestion.create');

        $data = $request->validate([
            'titre' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::in([
                'RECOMMANDATION', 'ALERTE', 'PROPOSITION_ACHAT', 'PARTENARIAT',
                'BESOIN_STRATEGIQUE', 'AMELIORATION', 'STOCK', 'FOURNISSEUR', 'RUPTURE', 'ANALYSE',
            ])],
            'contenu' => ['required', 'string', 'min:20', 'max:20000'],
            'priorite' => ['nullable', Rule::in(['NORMALE', 'HAUTE', 'URGENTE'])],
            'demande_id' => ['nullable', 'integer', 'exists:demandes_approvisionnement,id'],
            'soumettre' => ['nullable', 'boolean'],
        ]);

        $s = DB::transaction(function () use ($data, $actor) {
            $soumettre = !empty($data['soumettre']);
            $s = ApproSuggestionDg::create([
                'numero_suggestion' => $this->nextNumero(),
                'titre' => $data['titre'],
                'type' => $data['type'] ?? 'RECOMMANDATION',
                'contenu' => $data['contenu'],
                'statut' => $soumettre ? 'SOUMISE' : 'BROUILLON',
                'priorite' => $data['priorite'] ?? 'NORMALE',
                'demande_id' => $data['demande_id'] ?? null,
                'auteur_user_id' => $actor['user']->id,
                'version' => 1,
                'submitted_at' => $soumettre ? now() : null,
            ]);

            $this->logEvent($s, $actor, $soumettre ? 'SOUMISE' : 'CREEE', $s->titre);
            AuditLog::create([
                'auteur_nom' => $actor['nom'],
                'role' => $actor['role'],
                'action' => $soumettre ? 'SUG_DG_SOUMISE' : 'SUG_DG_CREEE',
                'details' => $s->numero_suggestion,
                'ip_address' => request()->ip(),
            ]);

            return $s->fresh(['auteur', 'demande']);
        });

        return response()->json(['success' => true, 'suggestion' => $s], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'suggestion.create');

        $s = ApproSuggestionDg::findOrFail($id);
        if (!in_array($s->statut, ['BROUILLON', 'CORRECTION_DEMANDEE'], true)) {
            return response()->json(['success' => false, 'message' => 'Modification interdite dans cet etat.'], 422);
        }
        if ((int) $s->auteur_user_id !== (int) $actor['user']->id && $actor['user']->code_role !== 'APPRO_NATIONAL') {
            abort(403, 'Seule l auteur ou la Direction Appro peut modifier.');
        }

        $data = $request->validate([
            'titre' => ['sometimes', 'string', 'max:255'],
            'contenu' => ['sometimes', 'string', 'min:20', 'max:20000'],
            'type' => ['sometimes', Rule::in([
                'RECOMMANDATION', 'ALERTE', 'PROPOSITION_ACHAT', 'PARTENARIAT',
                'BESOIN_STRATEGIQUE', 'AMELIORATION', 'STOCK', 'FOURNISSEUR', 'RUPTURE', 'ANALYSE',
            ])],
            'priorite' => ['sometimes', Rule::in(['NORMALE', 'HAUTE', 'URGENTE'])],
        ]);

        if ($s->statut === 'CORRECTION_DEMANDEE') {
            $s->version = $s->version + 1;
            $s->motif_correction = null;
        }

        $s->fill($data);
        $s->save();

        $this->logEvent($s, $actor, 'MODIFIEE', 'Version '.$s->version);

        return response()->json(['success' => true, 'suggestion' => $s->fresh(['auteur', 'demande'])]);
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'suggestion.submit');

        $s = ApproSuggestionDg::findOrFail($id);
        if (!in_array($s->statut, ['BROUILLON', 'CORRECTION_DEMANDEE'], true)) {
            return response()->json(['success' => false, 'message' => 'Soumission impossible dans cet etat.'], 422);
        }

        $s->statut = 'SOUMISE';
        $s->submitted_at = now();
        $s->save();

        $this->logEvent($s, $actor, 'SOUMISE', 'Soumise au DG National');
        AuditLog::create([
            'auteur_nom' => $actor['nom'],
            'role' => $actor['role'],
            'action' => 'SUG_DG_SOUMISE',
            'details' => "{$s->numero_suggestion} v{$s->version}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['success' => true, 'suggestion' => $s->fresh(['auteur', 'demande'])]);
    }

    public function dgDecide(Request $request, int $id): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'suggestion.decide');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['EN_ETUDE', 'ACCEPTEE', 'REFUSEE', 'CORRECTION_DEMANDEE', 'MISE_EN_OEUVRE', 'CLOTUREE'])],
            'commentaire' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $s = ApproSuggestionDg::findOrFail($id);

        $allowedFrom = [
            'SOUMISE' => ['EN_ETUDE', 'ACCEPTEE', 'REFUSEE', 'CORRECTION_DEMANDEE'],
            'EN_ETUDE' => ['ACCEPTEE', 'REFUSEE', 'CORRECTION_DEMANDEE', 'MISE_EN_OEUVRE'],
            'ACCEPTEE' => ['MISE_EN_OEUVRE', 'CLOTUREE'],
            'MISE_EN_OEUVRE' => ['CLOTUREE'],
            'CORRECTION_DEMANDEE' => [], // attend retour Appro
        ];

        $from = $s->statut;
        if (!isset($allowedFrom[$from]) || !in_array($data['decision'], $allowedFrom[$from], true)) {
            return response()->json([
                'success' => false,
                'message' => "Transition interdite : {$from} → {$data['decision']}",
            ], 422);
        }

        if ($data['decision'] === 'CORRECTION_DEMANDEE' && strlen(trim($data['commentaire'])) < 5) {
            return response()->json(['success' => false, 'message' => 'Motif de correction obligatoire.'], 422);
        }

        $s->statut = $data['decision'];
        $s->dg_user_id = $actor['user']->id;
        $s->dg_decision_at = now();
        $s->dg_commentaire = $data['commentaire'];
        if ($data['decision'] === 'CORRECTION_DEMANDEE') {
            $s->motif_correction = $data['commentaire'];
        }
        $s->save();

        $this->logEvent($s, $actor, 'DG_'.$data['decision'], $data['commentaire']);
        AuditLog::create([
            'auteur_nom' => $actor['nom'],
            'role' => $actor['role'],
            'action' => 'SUG_DG_'.$data['decision'],
            'details' => "{$s->numero_suggestion} — {$data['commentaire']}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['success' => true, 'suggestion' => $s->fresh(['auteur', 'demande', 'dg', 'evenements'])]);
    }
}
