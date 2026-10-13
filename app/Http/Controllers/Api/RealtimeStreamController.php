<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UrgenceSanitaire;
use App\Models\DemandeApprovisionnement;
use App\Models\AntenneProvinciale;
use App\Models\User;
use App\Services\OpnAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Realtime OPN — snapshot JSON (polling) prioritaire.
 * SSE conservé mais court : artisan serve est mono-thread et ne doit jamais rester bloqué.
 */
class RealtimeStreamController extends Controller
{
    public function __construct(protected OpnAuthorizationService $authService)
    {
    }

    /**
     * Snapshot non bloquant — multi-utilisateurs / multi-provinces via polling front.
     */
    public function snapshot(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'type' => 'ERROR',
                'message' => 'Non authentifie. Token Sanctum requis.',
            ], 401);
        }

        $scopedAntenneId = $this->scopeAntenneId($user);

        $urgencesQ = UrgenceSanitaire::where('statut', '!=', 'RESOLU');
        $ordersQ = DemandeApprovisionnement::whereNotIn('statut', ['RECU_CONFORME', 'CLOTUREE', 'ANNULEE']);
        $antennesQ = AntenneProvinciale::query();

        if ($scopedAntenneId) {
            $urgencesQ->where('antenne_id', $scopedAntenneId);
            $ordersQ->where('antenne_id', $scopedAntenneId);
            $antennesQ->where('id', $scopedAntenneId);
        }

        $latestUrgence = (clone $urgencesQ)->orderByDesc('created_at')->first();

        return response()->json([
            'success' => true,
            'type' => 'SNAPSHOT',
            'timestamp' => now()->toIso8601String(),
            'scope' => $scopedAntenneId ? 'PROVINCE' : 'NATIONAL',
            'antenne_id' => $scopedAntenneId,
            'active_emergencies_count' => $urgencesQ->count(),
            'active_orders_count' => $ordersQ->count(),
            'provinces_status' => $antennesQ->pluck('statut_stock', 'code_antenne'),
            'latest_emergency' => $latestUrgence ? [
                'id' => $latestUrgence->id,
                'titre' => $latestUrgence->titre,
                'lieu_precis' => $latestUrgence->lieu_precis,
                'created_at' => optional($latestUrgence->created_at)->toIso8601String(),
            ] : null,
        ]);
    }

    /**
     * SSE déprécié en local — réponse courte pour ne pas saturuer php artisan serve.
     * Preferer GET /realtime/snapshot (polling).
     */
    public function stream(Request $request): StreamedResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return new StreamedResponse(function () {
                echo 'data: '.json_encode([
                    'type' => 'ERROR',
                    'message' => 'Non authentifie. Token Sanctum requis.',
                ])."\n\n";
            }, 401, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
            ]);
        }

        $scopedAntenneId = $this->scopeAntenneId($user);

        $response = new StreamedResponse(function () use ($scopedAntenneId) {
            // Une seule frame puis fin — le front ne doit PAS se reconnecter en boucle.
            $urgencesQ = UrgenceSanitaire::where('statut', '!=', 'RESOLU');
            $ordersQ = DemandeApprovisionnement::whereNotIn('statut', ['RECU_CONFORME', 'CLOTUREE', 'ANNULEE']);
            if ($scopedAntenneId) {
                $urgencesQ->where('antenne_id', $scopedAntenneId);
                $ordersQ->where('antenne_id', $scopedAntenneId);
            }

            $payload = [
                'type' => 'CONNECTED',
                'mode' => 'oneshot',
                'timestamp' => now()->toIso8601String(),
                'message' => 'SSE oneshot — utiliser /realtime/snapshot pour le multi-utilisateurs.',
                'active_emergencies_count' => $urgencesQ->count(),
                'active_orders_count' => $ordersQ->count(),
            ];
            echo 'data: '.json_encode($payload)."\n\n";
            if (function_exists('ob_flush')) {
                @ob_flush();
            }
            flush();
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'close');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    protected function scopeAntenneId(User $user): ?int
    {
        $scope = OpnAuthorizationService::ROLES_PERMISSIONS[$user->code_role]['scope'] ?? 'PROVINCE';
        if (in_array($scope, ['ANTENNA', 'PROVINCE'], true) && $user->province_id) {
            return (int) $user->province_id;
        }

        return null;
    }

    protected function resolveUser(Request $request): ?User
    {
        if ($request->user()) {
            return $request->user();
        }

        $bearer = $request->bearerToken();
        $plain = $bearer ?: $request->query('token');
        if (!$plain || !is_string($plain)) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($plain);
        if (!$accessToken || !$accessToken->tokenable instanceof User) {
            return null;
        }

        $expiration = config('sanctum.expiration');
        if ($expiration && $accessToken->created_at->lte(now()->subMinutes((int) $expiration))) {
            return null;
        }

        return $accessToken->tokenable;
    }
}
