<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siege;
use Illuminate\Http\JsonResponse;

class OrganisationController extends Controller
{
    public function index(): JsonResponse
    {
        $sieges = Siege::with([
            'antennes.directions.services',
            'antennes.agents',
            'directions.services',
            'directions.agents',
            'agents.antenne',
            'agents.direction',
            'agents.service',
        ])->orderBy('nom')->get();

        return response()->json([
            'success' => true,
            'sieges' => $sieges,
        ]);
    }
}
