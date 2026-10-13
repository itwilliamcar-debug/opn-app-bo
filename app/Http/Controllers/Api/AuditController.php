<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\OpnAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __construct(protected OpnAuthorizationService $authService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Non authentifie.'], 401);
        }

        if (!$this->authService->can($user, 'audit.view')
            && !$this->authService->can($user, 'audit.trail.view')
            && !$this->authService->can($user, 'system.logs')) {
            return response()->json([
                'success' => false,
                'message' => 'Acces refuse au journal d\'audit ANINF.',
            ], 403);
        }

        $limit = min(200, max(1, (int) $request->query('limit', 50)));

        $logs = AuditLog::orderBy('created_at', 'desc')->take($limit)->get();

        return response()->json([
            'success' => true,
            'count' => $logs->count(),
            'logs' => $logs,
        ]);
    }
}
