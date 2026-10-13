<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NiveauAcces;
use Illuminate\Http\Request;

class NiveauAccesController extends Controller
{
    /**
     * Liste tous les niveaux d'accès
     */
    public function index()
    {
        $niveaux = NiveauAcces::active()
            ->byHierarchy()
            ->with(['direction', 'modules'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $niveaux,
        ]);
    }

    /**
     * Liste les niveaux d'accès d'une direction
     */
    public function byDirection($directionId)
    {
        $niveaux = NiveauAcces::where('direction_id', $directionId)
            ->active()
            ->byHierarchy()
            ->with(['modules'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $niveaux,
        ]);
    }

    /**
     * Affiche un niveau d'accès spécifique
     */
    public function show($id)
    {
        $niveau = NiveauAcces::with(['direction', 'modules'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $niveau,
        ]);
    }

    /**
     * Crée un nouveau niveau d'accès
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'direction_id' => 'required|exists:directions,id',
            'code' => 'required|string|unique:niveau_acces,code',
            'nom' => 'required|string',
            'description' => 'nullable|string',
            'niveau_hierarchie' => 'nullable|integer',
            'permissions' => 'nullable|array',
            'actif' => 'nullable|boolean',
        ]);

        $niveau = NiveauAcces::create($validated);

        return response()->json([
            'success' => true,
            'data' => $niveau,
        ], 201);
    }

    /**
     * Met à jour un niveau d'accès
     */
    public function update(Request $request, $id)
    {
        $niveau = NiveauAcces::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|string|unique:niveau_acces,code,' . $id,
            'nom' => 'sometimes|string',
            'description' => 'nullable|string',
            'niveau_hierarchie' => 'nullable|integer',
            'permissions' => 'nullable|array',
            'actif' => 'nullable|boolean',
        ]);

        $niveau->update($validated);

        return response()->json([
            'success' => true,
            'data' => $niveau,
        ]);
    }

    /**
     * Supprime un niveau d'accès
     */
    public function destroy($id)
    {
        $niveau = NiveauAcces::findOrFail($id);
        $niveau->delete();

        return response()->json([
            'success' => true,
            'message' => 'Niveau d\'accès supprimé avec succès',
        ]);
    }

    /**
     * Attache un module à un niveau d'accès
     */
    public function attachModule(Request $request, $niveauId)
    {
        $validated = $request->validate([
            'module_id' => 'required|exists:direction_modules,id',
        ]);

        $niveau = NiveauAcces::findOrFail($niveauId);
        $niveau->modules()->attach($validated['module_id']);

        return response()->json([
            'success' => true,
            'message' => 'Module attaché avec succès',
        ]);
    }

    /**
     * Détache un module d'un niveau d'accès
     */
    public function detachModule($niveauId, $moduleId)
    {
        $niveau = NiveauAcces::findOrFail($niveauId);
        $niveau->modules()->detach($moduleId);

        return response()->json([
            'success' => true,
            'message' => 'Module détaché avec succès',
        ]);
    }
}
