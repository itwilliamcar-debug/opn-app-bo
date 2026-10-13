<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MissionLogistique;
use App\Models\Livraison;
use App\Models\PreuveLivraison;
use App\Models\RapportMission;
use App\Models\DemandeApprovisionnement;
use App\Models\AuditLog;
use App\Services\OpnAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LogistiqueController extends Controller
{
    public function __construct(
        protected OpnAuthorizationService $authService
    ) {
    }

    /**
     * Liste des missions logistiques
     */
    public function getMissions(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = MissionLogistique::with(['convoyeur', 'antenneDepart', 'antenneDestination', 'livraisons.structure', 'rapports']);

        if ($user && $user->code_role === 'CONVOYEUR') {
            $query->where('convoyeur_id', $user->id);
        }

        $missions = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'count' => $missions->count(),
            'missions' => $missions,
        ]);
    }

    /**
     * Création d'une nouvelle mission de convoi
     */
    public function createMission(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'convoi_nom' => 'required|string|max:200',
            'type_transport' => 'required|string',
            'vehicule_immat' => 'nullable|string',
            'vehicule_type' => 'nullable|string',
            'chauffeur_ou_agent' => 'required|string',
            'chef_convoi_nom' => 'nullable|string',
            'convoyeur_id' => 'nullable|exists:users,id',
            'antenne_depart_id' => 'required|exists:antennes_provinciales,id',
            'antenne_destination_id' => 'required|exists:antennes_provinciales,id',
            'consignes_securite' => 'nullable|string',
            'demande_ids' => 'nullable|array',
        ]);

        $codeMission = 'MIS-2026-' . rand(100, 999);

        $mission = MissionLogistique::create([
            'code_mission' => $codeMission,
            'ordre_mission_ref' => 'ODM-OPN-' . rand(1000, 9999),
            'convoi_nom' => $validated['convoi_nom'],
            'type_transport' => $validated['type_transport'],
            'vehicule_immat' => $validated['vehicule_immat'] ?? 'OPN-GAB-044',
            'vehicule_type' => $validated['vehicule_type'] ?? 'Camion Frigorifique Isotherme 12T',
            'chauffeur_ou_agent' => $validated['chauffeur_ou_agent'],
            'chef_convoi_nom' => $validated['chef_convoi_nom'] ?? 'Cdt. Sécurité Convois',
            'convoyeur_id' => $validated['convoyeur_id'] ?? null,
            'antenne_depart_id' => $validated['antenne_depart_id'],
            'antenne_destination_id' => $validated['antenne_destination_id'],
            'statut' => 'PLANIFIEE',
            'temperature_actuelle' => 4.2,
            'temperature_conforme' => true,
            'kilometrage_depart' => rand(15000, 85000),
            'consignes_securite' => $validated['consignes_securite'] ?? 'Vérification continue capteurs thermiques 2°C–8°C.',
        ]);

        // Rattacher les demandes sélectionnées sous forme de livraisons
        if (!empty($validated['demande_ids'])) {
            foreach ($validated['demande_ids'] as $demandeId) {
                $demande = DemandeApprovisionnement::find($demandeId);
                if ($demande) {
                    $qrToken = 'QR-LIV-' . hash('sha256', $demande->numero_demande . $codeMission . rand(1000, 9999));
                    Livraison::create([
                        'code_livraison' => 'LIV-' . $demande->numero_demande,
                        'mission_id' => $mission->id,
                        'demande_id' => $demande->id,
                        'structure_id' => $demande->structure_id,
                        'statut' => 'EN_PREPARATION',
                        'qr_token' => $qrToken,
                        'token_expires_at' => now()->addDays(7),
                    ]);
                    $demande->update(['statut' => 'EN_PREPARATION', 'etape_index' => 3]);
                }
            }
        }

        AuditLog::create([
            'auteur_nom' => $request->user() ? $request->user()->name : 'Gestionnaire Logistique',
            'role' => 'GESTIONNAIRE_LOGISTIQUE',
            'action' => 'CREATION_MISSION_CONVOI',
            'details' => "Création de la mission de transport {$codeMission} ({$mission->convoi_nom}).",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Mission logistique {$codeMission} créée avec succès.",
            'mission' => $mission->load(['livraisons.demande', 'antenneDepart', 'antenneDestination']),
        ], 201);
    }

    /**
     * Démarrage et étapes de mission par le Convoyeur
     */
    public function updateMissionStatus(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'statut' => 'required|in:CHARGEMENT_CONFIRME,EN_ROUTE,ARRIVEE_DESTINATION,LIVRAISONS_TERMINEES,INCIDENT_DECLARE',
            'convoyeur_nom' => 'required|string',
            'temperature' => 'nullable|numeric',
            'incident_description' => 'nullable|string',
            'kilometrage' => 'nullable|integer',
        ]);

        $mission = MissionLogistique::findOrFail($id);
        $statut = $validated['statut'];

        $updateData = ['statut' => $statut];
        if (isset($validated['temperature'])) {
            $updateData['temperature_actuelle'] = $validated['temperature'];
            $updateData['temperature_conforme'] = ($validated['temperature'] >= 2.0 && $validated['temperature'] <= 8.0);
        }
        if ($statut === 'EN_ROUTE' && !$mission->date_depart) {
            $updateData['date_depart'] = now();
        }
        if ($statut === 'ARRIVEE_DESTINATION') {
            $updateData['date_arrivee_effective'] = now();
        }
        if ($statut === 'INCIDENT_DECLARE') {
            $updateData['incident_description'] = $validated['incident_description'];
        }

        $mission->update($updateData);

        AuditLog::create([
            'auteur_nom' => $validated['convoyeur_nom'],
            'role' => 'CONVOYEUR',
            'action' => 'STATUT_MISSION_' . $statut,
            'details' => "Mise à jour de la mission {$mission->code_mission} au statut {$statut}.",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Statut de la mission mis à jour ({$statut}).",
            'mission' => $mission,
        ]);
    }

    /**
     * Article 23 : Vérification du QR Code de Livraison avec contrôle anti-rejeu côté serveur
     */
    public function verifyQrCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_token' => 'required|string',
            'scanned_by_nom' => 'required|string',
        ]);

        $livraison = Livraison::with(['demande.lignes.produit', 'structure', 'mission.antenneDestination'])
            ->where('qr_token', $validated['qr_token'])
            ->first();

        if (!$livraison) {
            return response()->json([
                'success' => false,
                'error_code' => 'QR_INVALID',
                'message' => 'QR Code non reconnu ou falsifié. Dossier introuvable.',
            ], 404);
        }

        // Contrôle anti-rejeu : Vérifier si la livraison est déjà clôturée ou réceptionnée
        if ($livraison->is_closed || $livraison->statut === 'CLOTUREE' || $livraison->statut === 'LIVREE') {
            return response()->json([
                'success' => false,
                'error_code' => 'QR_ALREADY_USED',
                'message' => "Tentative de réutilisation détectée : Ce dossier de livraison ({$livraison->code_livraison}) a DÉJÀ été réceptionné et clôturé le " . ($livraison->closed_at ? $livraison->closed_at->format('d/m/Y H:i') : 'précédemment') . '.',
                'livraison' => $livraison,
            ], 422);
        }

        // Marquer comme scanné
        $livraison->update([
            'is_qr_scanned' => true,
            'scanned_at' => now(),
            'scanned_by_nom' => $validated['scanned_by_nom'],
            'statut' => 'EN_RECEPTION',
        ]);

        AuditLog::create([
            'auteur_nom' => $validated['scanned_by_nom'],
            'role' => 'CONVOYEUR',
            'action' => 'SCAN_QR_LIVRAISON',
            'details' => "Scan certifié du QR Code pour la livraison {$livraison->code_livraison}.",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'QR Code authentifié avec succès. Prêt pour pointage et émargement.',
            'livraison' => $livraison,
        ]);
    }

    /**
     * Articles 24 & 25 : Enregistrement de la Preuve de Livraison signée par le Bénéficiaire
     */
    public function signOffDelivery(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom_receptionnaire' => 'required|string',
            'fonction_receptionnaire' => 'required|string',
            'contact_receptionnaire' => 'nullable|string',
            'quantites_recues_json' => 'required|array',
            'ecarts_declares' => 'nullable|string',
            'reserves_qualite' => 'nullable|string',
            'produits_endommages' => 'nullable|string',
            'signature_image_data' => 'nullable|string', // Base64 de la signature tactile
        ]);

        $livraison = Livraison::with('demande')->findOrFail($id);

        if ($livraison->is_closed) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de modifier une livraison déjà clôturée.',
            ], 422);
        }

        $signatureHash = 'OPN-SIG-REC-' . hash('sha256', $livraison->code_livraison . $validated['nom_receptionnaire'] . now());

        $preuve = PreuveLivraison::create([
            'livraison_id' => $livraison->id,
            'nom_receptionnaire' => $validated['nom_receptionnaire'],
            'fonction_receptionnaire' => $validated['fonction_receptionnaire'],
            'contact_receptionnaire' => $validated['contact_receptionnaire'] ?? null,
            'quantites_recues_json' => $validated['quantites_recues_json'],
            'ecarts_declares' => $validated['ecarts_declares'] ?? null,
            'reserves_qualite' => $validated['reserves_qualite'] ?? null,
            'produits_endommages' => $validated['produits_endommages'] ?? null,
            'signature_electronique_hash' => $signatureHash,
            'signature_image_data' => $validated['signature_image_data'] ?? null,
            'date_signature' => now(),
            'ip_address' => $request->ip(),
        ]);

        $livraison->update([
            'statut' => 'LIVREE',
            'is_closed' => false, // Passera à clôturée après validation Logistique & Appro
        ]);

        $livraison->demande->update([
            'statut' => 'RECU_CONFORME',
            'date_reception_effective' => now(),
            'etape_index' => 5,
        ]);

        AuditLog::create([
            'auteur_nom' => $validated['nom_receptionnaire'],
            'role' => $validated['fonction_receptionnaire'],
            'action' => 'SIGNATURE_RECEPTION_LIVRAISON',
            'details' => "Émargement électronique officiel pour la livraison {$livraison->code_livraison} (Signature: {$signatureHash}).",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Preuve de livraison générée et signée avec succès. Hash certifié : {$signatureHash}",
            'preuve' => $preuve,
            'livraison' => $livraison->load('preuve'),
        ]);
    }

    /**
     * Article 26 : Soumission du Rapport de Mission par le Convoyeur
     */
    public function submitMissionReport(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'convoyeur_nom' => 'required|string',
            'heure_depart' => 'nullable|string',
            'heure_arrivee' => 'nullable|string',
            'km_total' => 'nullable|integer',
            'temperature_min' => 'nullable|numeric',
            'temperature_max' => 'nullable|numeric',
            'produits_transportes_resume' => 'nullable|string',
            'ecarts_constates' => 'nullable|string',
            'incidents_route' => 'nullable|string',
            'observations' => 'nullable|string',
            'signature_convoyeur' => 'nullable|string',
        ]);

        $mission = MissionLogistique::findOrFail($id);

        $rapport = RapportMission::create([
            'mission_id' => $mission->id,
            'convoyeur_nom' => $validated['convoyeur_nom'],
            'heure_depart' => $validated['heure_depart'] ?? '06:30',
            'heure_arrivee' => $validated['heure_arrivee'] ?? '16:45',
            'km_total' => $validated['km_total'] ?? 420,
            'temperature_min' => $validated['temperature_min'] ?? 3.8,
            'temperature_max' => $validated['temperature_max'] ?? 5.2,
            'produits_transportes_resume' => $validated['produits_transportes_resume'] ?? 'Solutés, antipaludiques ACT, vaccins',
            'ecarts_constates' => $validated['ecarts_constates'] ?? 'Aucun écart',
            'incidents_route' => $validated['incidents_route'] ?? 'Néant, traversée Setrag/Route normale',
            'observations' => $validated['observations'] ?? 'Conforme aux normes de chaîne du froid BPF',
            'signature_convoyeur' => $validated['signature_convoyeur'] ?? ('SIG-CVY-' . hash('crc32', $validated['convoyeur_nom'] . now())),
            'statut' => 'SOUMIS',
        ]);

        $mission->update(['statut' => 'RAPPORT_SOUMIS']);

        AuditLog::create([
            'auteur_nom' => $validated['convoyeur_nom'],
            'role' => 'CONVOYEUR',
            'action' => 'DEPOT_RAPPORT_MISSION',
            'details' => "Dépôt du rapport de mission {$mission->code_mission}.",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rapport de mission transmis à la Logistique et à l\'Approvisionnement.',
            'rapport' => $rapport,
        ], 201);
    }

    /**
     * Article 25 : Clôture définitive du dossier par Logistique et Approvisionnement
     */
    public function closeDeliveryWorkflow(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifie. Session Sanctum requise.',
            ], 401);
        }

        if (!$this->authService->can($user, 'delivery.close.national')
            && !$this->authService->can($user, 'delivery.close')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé : clôture de livraison réservée à la Logistique ou à l\'Approvisionnement national.',
            ], 403);
        }

        $validated = $request->validate([
            'validateur_nom' => 'required|string',
            'role_validateur' => 'required|in:GESTIONNAIRE_LOGISTIQUE,APPRO_NATIONAL,SUPER_ADMIN',
            'commentaires' => 'nullable|string',
        ]);

        $livraison = Livraison::with('demande')->findOrFail($id);

        $livraison->update([
            'statut' => 'CLOTUREE',
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $livraison->demande->update([
            'statut' => 'CLOTUREE',
            'etape_index' => 6,
        ]);

        AuditLog::create([
            'auteur_nom' => $user->name,
            'role' => $user->role ?? $user->code_role ?? $validated['role_validateur'],
            'action' => 'CLOTURE_LIVRAISON_NATIONALE',
            'details' => "Clôture définitive du dossier {$livraison->code_livraison} par {$user->name}.",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Dossier de livraison {$livraison->code_livraison} CLÔTURÉ avec succès.",
            'livraison' => $livraison,
        ]);
    }
}
