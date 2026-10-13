<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Direction;
use Illuminate\Http\Request;

class DirectionController extends Controller
{
    /**
     * Liste toutes les directions actives
     */
    public function index()
    {
        $directions = Direction::active()
            ->ordered()
            ->with(['modules' => function ($query) {
                $query->active()->ordered();
            }])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $directions,
        ]);
    }

    /**
     * Affiche une direction spécifique avec ses modules
     */
    public function show($id)
    {
        $direction = Direction::with(['modules' => function ($query) {
            $query->active()->ordered();
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $direction,
        ]);
    }

    /**
     * Crée une nouvelle direction (SuperAdmin uniquement)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:directions,code',
            'nom' => 'required|string',
            'description' => 'nullable|string',
            'couleur' => 'nullable|string',
            'icone' => 'nullable|string',
            'ordre' => 'nullable|integer',
            'actif' => 'nullable|boolean',
        ]);

        $direction = Direction::create($validated);

        return response()->json([
            'success' => true,
            'data' => $direction,
        ], 201);
    }

    /**
     * Met à jour une direction
     */
    public function update(Request $request, $id)
    {
        $direction = Direction::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|string|unique:directions,code,' . $id,
            'nom' => 'sometimes|string',
            'description' => 'nullable|string',
            'couleur' => 'nullable|string',
            'icone' => 'nullable|string',
            'ordre' => 'nullable|integer',
            'actif' => 'nullable|boolean',
        ]);

        $direction->update($validated);

        return response()->json([
            'success' => true,
            'data' => $direction,
        ]);
    }

    /**
     * Supprime une direction
     */
    public function destroy($id)
    {
        $direction = Direction::findOrFail($id);
        $direction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Direction supprimée avec succès',
        ]);
    }
}
