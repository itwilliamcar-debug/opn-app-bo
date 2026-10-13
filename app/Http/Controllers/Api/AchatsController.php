<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fournisseur;
use App\Models\CommandeFournisseur;
use App\Models\LigneCommandeFournisseur;
use App\Models\ProduitPharmaceutique;
use App\Models\AuditLog;
use App\Services\OpnAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AchatsController extends Controller
{
    public function __construct(protected OpnAuthorizationService $authService)
    {
    }

    public function getSuppliers(Request $request): JsonResponse
    {
        $fournisseurs = Fournisseur::withCount('commandes')->orderBy('nom')->get();

        return response()->json([
            'success' => true,
            'count' => $fournisseurs->count(),
            'fournisseurs' => $fournisseurs,
        ]);
    }

    public function getPurchaseOrders(Request $request): JsonResponse
    {
        $commandes = CommandeFournisseur::with(['fournisseur', 'acheteur', 'lignes.produit'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'count' => $commandes->count(),
            'commandes' => $commandes,
        ]);
    }

    public function createPurchaseOrder(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifie. Session Sanctum requise.',
            ], 401);
        }

        $validated = $request->validate([
            'fournisseur_id' => 'required|exists:fournisseurs,id',
            'lignes' => 'required|array|min:1',
            'lignes.*.produit_id' => 'required|exists:produits_pharmaceutiques,id',
            'lignes.*.quantite_commandee' => 'required|integer|min:1',
            'lignes.*.prix_unitaire_xaf' => 'required|numeric|min:1',
            'conditions_paiement' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $numero = 'CMD-ACHAT-2026-' . rand(100, 999);

        $montantTotal = 0;
        foreach ($validated['lignes'] as $ligne) {
            $montantTotal += $ligne['quantite_commandee'] * $ligne['prix_unitaire_xaf'];
        }

        $commande = CommandeFournisseur::create([
            'numero_commande' => $numero,
            'fournisseur_id' => $validated['fournisseur_id'],
            'acheteur_id' => $user->id,
            'montant_total_xaf' => $montantTotal,
            'statut' => 'EN_VALIDATION_DIR',
            'date_commande' => now(),
            'date_livraison_prevue' => now()->addDays(35),
            'conditions_paiement' => $validated['conditions_paiement'] ?? 'Lettre de Credit Irrevocable (LC/OMS)',
            'notes' => $validated['notes'] ?? 'Achat groupe international medicaments essentiels OPN',
        ]);

        foreach ($validated['lignes'] as $ligne) {
            LigneCommandeFournisseur::create([
                'commande_fournisseur_id' => $commande->id,
                'produit_id' => $ligne['produit_id'],
                'quantite_commandee' => $ligne['quantite_commandee'],
                'prix_unitaire_xaf' => $ligne['prix_unitaire_xaf'],
                'total_ligne_xaf' => $ligne['quantite_commandee'] * $ligne['prix_unitaire_xaf'],
            ]);
        }

        AuditLog::create([
            'auteur_nom' => $user->name,
            'role' => $user->role ?? $user->code_role ?? 'ACHATS',
            'action' => 'CREATION_COMMANDE_FOURNISSEUR',
            'details' => "Creation de la commande d'achat {$numero} d'un montant de {$montantTotal} FCFA.",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Bon de commande fournisseur {$numero} genere avec succes et soumis au circuit de validation institutionnel.",
            'commande' => $commande->load(['fournisseur', 'lignes.produit']),
        ], 201);
    }

    /**
     * Circuit Institutionnel de Validation des Commandes d'Achats :
     * DIR -> APPRO -> DAF -> DG
     * Role serveur obligatoire — ne fait pas confiance au client.
     */
    public function advanceWorkflow(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifie. Session Sanctum requise.',
            ], 401);
        }

        $validated = $request->validate([
            'etape' => 'required|in:VISA_DIR,VISA_APPRO,VISA_DAF,VISA_DG',
            'action' => 'required|in:APPROUVER,REJETER,DEMANDER_CORRECTION',
            'motif' => 'nullable|string',
        ]);

        $etape = $validated['etape'];
        $action = $validated['action'];

        $expectedRole = match ($etape) {
            'VISA_DIR' => 'DIR',
            'VISA_APPRO' => 'APPRO_NATIONAL',
            'VISA_DAF' => 'DAF',
            'VISA_DG' => 'DG',
            default => null,
        };

        $permission = match ($etape) {
            'VISA_DIR' => 'demand.visa.dir',
            'VISA_DAF' => 'demand.visa.daf',
            'VISA_DG' => 'demand.visa.dg',
            'VISA_APPRO' => null,
            default => null,
        };

        if ($user->code_role !== $expectedRole) {
            return response()->json([
                'success' => false,
                'message' => "Acces refuse : etape {$etape} reservee au profil {$expectedRole}.",
            ], 403);
        }

        if ($permission && !$this->authService->can($user, $permission)) {
            return response()->json([
                'success' => false,
                'message' => "Acces refuse : permission insuffisante pour {$etape}.",
            ], 403);
        }

        $commande = CommandeFournisseur::findOrFail($id);
        $userNom = $user->name;

        $expectedStatut = match ($etape) {
            'VISA_DIR' => 'EN_VALIDATION_DIR',
            'VISA_APPRO' => 'EN_VALIDATION_APPRO',
            'VISA_DAF' => 'EN_VALIDATION_DAF',
            'VISA_DG' => 'EN_VALIDATION_DG',
            default => null,
        };

        if ($expectedStatut && $commande->statut !== $expectedStatut && $action === 'APPROUVER') {
            return response()->json([
                'success' => false,
                'message' => "Commande non en attente de {$etape} (statut actuel : {$commande->statut}).",
            ], 422);
        }

        if ($action === 'REJETER') {
            $commande->update([
                'statut' => 'REJETE',
                'notes' => $commande->notes . " | Rejete par {$userNom} a l'etape {$etape} : " . ($validated['motif'] ?? 'Non justifie'),
            ]);

            AuditLog::create([
                'auteur_nom' => $userNom,
                'role' => $user->code_role,
                'action' => 'REJET_COMMANDE_FOURNISSEUR',
                'details' => "Rejet de la commande {$commande->numero_commande} a l'etape {$etape}.",
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Commande rejetee avec motif enregistre.',
                'commande' => $commande,
            ]);
        }

        if ($etape === 'VISA_DIR') {
            $commande->update([
                'visa_dir_nom' => $userNom,
                'visa_dir_at' => now(),
                'statut' => 'EN_VALIDATION_APPRO',
            ]);
        } elseif ($etape === 'VISA_APPRO') {
            $commande->update([
                'visa_appro_nom' => $userNom,
                'visa_appro_at' => now(),
                'statut' => 'EN_VALIDATION_DAF',
            ]);
        } elseif ($etape === 'VISA_DAF') {
            $commande->update([
                'visa_daf_nom' => $userNom,
                'visa_daf_at' => now(),
                'statut' => 'EN_VALIDATION_DG',
            ]);
        } elseif ($etape === 'VISA_DG') {
            $signatureHash = 'OPN-SIG-DG-' . hash('sha256', $commande->numero_commande . now() . $userNom);
            $qrToken = 'QR-CERT-' . strtoupper(substr(md5($signatureHash), 0, 12));

            $commande->update([
                'visa_dg_nom' => $userNom,
                'visa_dg_at' => now(),
                'statut' => 'VALIDE_SIGNE',
                'signature_hash' => $signatureHash,
                'qr_token' => $qrToken,
            ]);
        }

        AuditLog::create([
            'auteur_nom' => $userNom,
            'role' => $user->code_role,
            'action' => "VALIDATION_{$etape}",
            'details' => "Validation {$etape} apposee sur la commande {$commande->numero_commande}.",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Etape {$etape} validee avec succes pour la commande {$commande->numero_commande}.",
            'commande' => $commande->load(['fournisseur', 'lignes.produit']),
        ]);
    }
}
