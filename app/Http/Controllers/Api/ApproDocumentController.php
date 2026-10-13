<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApproDocument;
use App\Models\ApproDocumentVersion;
use App\Models\ApproSuggestionDg;
use App\Models\AuditLog;
use App\Models\DemandeApprovisionnement;
use App\Models\User;
use App\Services\OpnAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GED Appro — documents versionnés, stockage privé, téléchargement authentifié.
 */
class ApproDocumentController extends Controller
{
    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'text/csv',
    ];

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
            abort(403, 'Permission document insuffisante.');
        }
    }

    protected function nextCode(): string
    {
        $year = now()->format('Y');
        $count = ApproDocument::where('code_document', 'like', "DOC-{$year}-%")->count() + 1;

        return sprintf('DOC-%s-%04d', $year, $count);
    }

    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'document.view');

        $query = ApproDocument::with(['createdBy', 'versions' => fn ($q) => $q->orderByDesc('numero_version')->limit(5)])
            ->orderByDesc('updated_at');

        if ($request->filled('demande_id')) {
            $query->where('demande_id', (int) $request->query('demande_id'));
        }
        if ($request->filled('suggestion_id')) {
            $query->where('suggestion_id', (int) $request->query('suggestion_id'));
        }
        if ($request->filled('statut') && $request->query('statut') !== 'ALL') {
            $query->where('statut', $request->query('statut'));
        } else {
            $query->where('statut', '!=', 'SUPPRIME');
        }

        $docs = $query->limit(200)->get();

        return response()->json(['success' => true, 'count' => $docs->count(), 'documents' => $docs]);
    }

    public function upload(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'document.upload');

        $data = $request->validate([
            'file' => ['required', 'file', 'max:15360'], // 15 Mo
            'titre' => ['required', 'string', 'max:255'],
            'categorie' => ['nullable', Rule::in([
                'DEMANDE', 'DEVIS', 'PROFORMA', 'FICHE_TECHNIQUE', 'CERTIFICAT',
                'CORRESPONDANCE', 'JUSTIFICATIF', 'RAPPORT', 'QUALITE', 'DOUANE', 'AUTRE',
            ])],
            'confidentialite' => ['nullable', Rule::in(['PUBLIC_INTERNE', 'RESTREINT', 'SECRET'])],
            'demande_id' => ['nullable', 'integer', 'exists:demandes_approvisionnement,id'],
            'suggestion_id' => ['nullable', 'integer', 'exists:appro_suggestions_dg,id'],
        ]);

        if (empty($data['demande_id']) && empty($data['suggestion_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Rattachez le document a une demande ou a une suggestion DG.',
            ], 422);
        }

        $file = $request->file('file');
        $mime = $file->getMimeType() ?: $file->getClientMimeType();
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            return response()->json([
                'success' => false,
                'message' => "Type MIME non autorise : {$mime}",
            ], 422);
        }

        $document = DB::transaction(function () use ($data, $file, $mime, $actor) {
            $checksum = hash_file('sha256', $file->getRealPath());
            $path = $file->store('documents/appro/'.now()->format('Y/m'), 'local');

            $doc = ApproDocument::create([
                'code_document' => $this->nextCode(),
                'demande_id' => $data['demande_id'] ?? null,
                'suggestion_id' => $data['suggestion_id'] ?? null,
                'titre' => $data['titre'],
                'categorie' => $data['categorie'] ?? 'AUTRE',
                'confidentialite' => $data['confidentialite'] ?? 'RESTREINT',
                'statut' => 'ACTIF',
                'version_courante' => 1,
                'created_by_user_id' => $actor['user']->id,
            ]);

            ApproDocumentVersion::create([
                'document_id' => $doc->id,
                'numero_version' => 1,
                'nom_fichier_original' => $file->getClientOriginalName(),
                'chemin_stockage' => $path,
                'disk' => 'local',
                'mime_type' => $mime,
                'taille_octets' => $file->getSize(),
                'checksum_sha256' => $checksum,
                'uploaded_by_user_id' => $actor['user']->id,
                'motif_version' => 'Version initiale',
            ]);

            if (!empty($data['demande_id'])) {
                $demande = DemandeApprovisionnement::find($data['demande_id']);
                if ($demande) {
                    $historique = $demande->historique_workflow ?? [];
                    $historique[] = [
                        'etape' => 'GOUV_DOCUMENT_AJOUTE',
                        'date' => now()->toIso8601String(),
                        'auteur' => $actor['nom'],
                        'details' => "{$doc->code_document} v1 — {$doc->titre}",
                        'valide' => true,
                    ];
                    $demande->historique_workflow = $historique;
                    $demande->save();
                }
            }

            AuditLog::create([
                'auteur_nom' => $actor['nom'],
                'role' => $actor['role'],
                'action' => 'GED_DOCUMENT_UPLOAD',
                'details' => "{$doc->code_document} v1",
                'ip_address' => request()->ip(),
            ]);

            return $doc->fresh(['versions', 'createdBy']);
        });

        return response()->json(['success' => true, 'document' => $document], 201);
    }

    public function replaceVersion(Request $request, int $id): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'document.replace');

        $data = $request->validate([
            'file' => ['required', 'file', 'max:15360'],
            'motif_version' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $doc = ApproDocument::findOrFail($id);
        if ($doc->statut === 'ARCHIVE') {
            return response()->json(['success' => false, 'message' => 'Document archive : remplacement interdit.'], 422);
        }

        $file = $request->file('file');
        $mime = $file->getMimeType() ?: $file->getClientMimeType();
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            return response()->json(['success' => false, 'message' => "Type MIME non autorise : {$mime}"], 422);
        }

        $document = DB::transaction(function () use ($doc, $file, $mime, $actor, $data) {
            $next = $doc->version_courante + 1;
            $checksum = hash_file('sha256', $file->getRealPath());
            $path = $file->store('documents/appro/'.now()->format('Y/m'), 'local');

            ApproDocumentVersion::create([
                'document_id' => $doc->id,
                'numero_version' => $next,
                'nom_fichier_original' => $file->getClientOriginalName(),
                'chemin_stockage' => $path,
                'disk' => 'local',
                'mime_type' => $mime,
                'taille_octets' => $file->getSize(),
                'checksum_sha256' => $checksum,
                'uploaded_by_user_id' => $actor['user']->id,
                'motif_version' => $data['motif_version'],
            ]);

            $doc->version_courante = $next;
            $doc->statut = 'ACTIF';
            $doc->save();

            AuditLog::create([
                'auteur_nom' => $actor['nom'],
                'role' => $actor['role'],
                'action' => 'GED_DOCUMENT_VERSION',
                'details' => "{$doc->code_document} v{$next} — {$data['motif_version']}",
                'ip_address' => request()->ip(),
            ]);

            return $doc->fresh(['versions', 'createdBy']);
        });

        return response()->json(['success' => true, 'document' => $document]);
    }

    public function download(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'document.download');

        $versionNum = $request->query('version');
        $doc = ApproDocument::findOrFail($id);

        if ($doc->confidentialite === 'SECRET' && !in_array($actor['user']->code_role, ['APPRO_NATIONAL', 'DG', 'SUPER_ADMIN'], true)) {
            return response()->json(['success' => false, 'message' => 'Document SECRET : acces refuse.'], 403);
        }

        $version = $versionNum
            ? ApproDocumentVersion::where('document_id', $doc->id)->where('numero_version', (int) $versionNum)->firstOrFail()
            : ApproDocumentVersion::where('document_id', $doc->id)->where('numero_version', $doc->version_courante)->firstOrFail();

        if (!Storage::disk($version->disk)->exists($version->chemin_stockage)) {
            return response()->json(['success' => false, 'message' => 'Fichier introuvable sur le stockage.'], 404);
        }

        AuditLog::create([
            'auteur_nom' => $actor['nom'],
            'role' => $actor['role'],
            'action' => 'GED_DOCUMENT_DOWNLOAD',
            'details' => "{$doc->code_document} v{$version->numero_version}",
            'ip_address' => $request->ip(),
        ]);

        return Storage::disk($version->disk)->download(
            $version->chemin_stockage,
            $version->nom_fichier_original,
            ['Content-Type' => $version->mime_type]
        );
    }

    public function archive(Request $request, int $id): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'document.archive');

        $data = $request->validate([
            'motif_archivage' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $doc = ApproDocument::findOrFail($id);
        if ($doc->statut === 'ARCHIVE') {
            return response()->json(['success' => false, 'message' => 'Deja archive.'], 422);
        }

        $doc->statut = 'ARCHIVE';
        $doc->archived_at = now();
        $doc->archived_by_user_id = $actor['user']->id;
        $doc->motif_archivage = $data['motif_archivage'];
        $doc->save();

        AuditLog::create([
            'auteur_nom' => $actor['nom'],
            'role' => $actor['role'],
            'action' => 'GED_DOCUMENT_ARCHIVE',
            'details' => "{$doc->code_document} — {$data['motif_archivage']}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['success' => true, 'document' => $doc]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $actor = $this->actor($request);
        $this->denyUnless($actor['user'], 'document.view');

        $doc = ApproDocument::with(['versions.uploadedBy', 'createdBy', 'demande'])->findOrFail($id);

        return response()->json(['success' => true, 'document' => $doc]);
    }
}
