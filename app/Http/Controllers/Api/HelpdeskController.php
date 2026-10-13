<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AntenneProvinciale;
use App\Models\AuditLog;
use App\Services\OpnAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HelpdeskController extends Controller
{
    protected OpnAuthorizationService $authService;

    public function __construct(OpnAuthorizationService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Article 43 : Récupère la destination IT imposée pour l'utilisateur
     */
    public function getItDestinationForUser(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user && $request->has('email')) {
            $user = User::where('email', $request->query('email'))->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non identifié.',
            ], 404);
        }

        $itInfo = $this->authService->getEnforcedItDestination($user);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'matricule_opn' => $user->matricule_opn,
                'telephone' => $user->telephone,
            ],
            'enforced_it_destination' => $itInfo,
        ]);
    }

    /**
     * Article 43 : Soumission d'une demande de réinitialisation de mot de passe
     * Le backend vérifie et force la destination IT selon le rattachement de l'utilisateur.
     * Toute tentative de forçage client est rejetée / corrigée.
     */
    public function requestPasswordReset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'motif' => 'required|string',
            'telephone_contact' => 'required|string',
            'details' => 'nullable|string',
            'it_destination_code' => 'nullable|string', // Envoyé par le client, mais RE-CONTRÔLÉ par le serveur
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun compte agent OPN ne correspond à cette adresse email.',
            ], 404);
        }

        $enforcedIt = $this->authService->getEnforcedItDestination($user);

        // Si le client a tenté d'envoyer une autre destination IT, le backend applique STRICTEMENT la destination imposée
        $effectiveDestination = $enforcedIt['it_destination_code'];
        $wasTampered = false;

        if (!empty($validated['it_destination_code']) && $validated['it_destination_code'] !== $effectiveDestination) {
            $wasTampered = true;
        }

        $ticketId = 'TICKET-IT-' . ($user->province_id ?? 'EST') . '-' . rand(1000, 9999);

        // Audit Trail ANINF
        AuditLog::create([
            'auteur_nom' => $user->name,
            'role' => $user->role ?? 'Agent OPN',
            'action' => 'DEMANDE_REINITIALISATION_MDP',
            'details' => "Demande de réinitialisation de mot de passe soumise pour {$user->name}. IT Destinataire imposée : {$enforcedIt['it_destination_label']} (Ticket: {$ticketId})." . ($wasTampered ? " [AVERTISSEMENT SÉCURITÉ : Tentative de contournement de la destination IT détectée et neutralisée]" : ""),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Votre demande a été transmise en haute priorité au {$enforcedIt['it_destination_label']}.",
            'ticket' => [
                'id' => $ticketId,
                'demandeur_nom' => $user->name,
                'email' => $user->email,
                'telephone_contact' => $validated['telephone_contact'],
                'it_destination_code' => $effectiveDestination,
                'it_destination_label' => $enforcedIt['it_destination_label'],
                'antenne_nom' => $enforcedIt['antenne_nom'],
                'chef_lieu' => $enforcedIt['chef_lieu'],
                'motif' => $validated['motif'],
                'date_demande' => now()->format('d/m/Y H:i'),
                'statut' => 'EN_ATTENTE_IT',
            ],
            'security_notice' => 'Destination IT validée et scellée selon votre rattachement provincial ANINF.',
        ], 201);
    }
}
