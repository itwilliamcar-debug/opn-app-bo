<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DirectionModule;
use Illuminate\Http\Request;

class DirectionModuleController extends Controller
{
    /**
     * Liste tous les modules d'une direction
     */
    public function index($directionId)
    {
        $modules = DirectionModule::where('direction_id', $directionId)
            ->active()
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $modules,
        ]);
    }

    /**
     * Affiche un module spécifique
     */
    public function show($id)
    {
        $module = DirectionModule::with('direction')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $module,
        ]);
    }

    /**
     * Crée un nouveau module
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'direction_id' => 'required|exists:directions,id',
            'code' => 'required|string',
            'nom' => 'required|string',
            'description' => 'nullable|string',
            'icone' => 'nullable|string',
            'route' => 'nullable|string',
            'ordre' => 'nullable|integer',
            'actif' => 'nullable|boolean',
        ]);

        $module = DirectionModule::create($validated);

        return response()->json([
            'success' => true,
            'data' => $module,
        ], 201);
    }

    /**
     * Met à jour un module
     */
    public function update(Request $request, $id)
    {
        $module = DirectionModule::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|string',
            'nom' => 'sometimes|string',
            'description' => 'nullable|string',
            'icone' => 'nullable|string',
            'route' => 'nullable|string',
            'ordre' => 'nullable|integer',
            'actif' => 'nullable|boolean',
        ]);

        $module->update($validated);

        return response()->json([
            'success' => true,
            'data' => $module,
        ]);
    }

    /**
     * Supprime un module
     */
    public function destroy($id)
    {
        $module = DirectionModule::findOrFail($id);
        $module->delete();

        return response()->json([
            'success' => true,
            'message' => 'Module supprimé avec succès',
        ]);
    }
}
