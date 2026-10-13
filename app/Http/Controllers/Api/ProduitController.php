<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProduitPharmaceutique;
use App\Models\LotProduit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProduitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProduitPharmaceutique::with(['lotsFefo']);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('designation', 'like', "%{$search}%")
                  ->orWhere('dci', 'like', "%{$search}%")
                  ->orWhere('code_produit', 'like', "%{$search}%");
            });
        }

        if ($request->has('categorie') && $request->categorie !== 'ALL') {
            $query->where('categorie', 'like', "%{$request->categorie}%");
        }

        $produits = $query->get();

        return response()->json([
            'success' => true,
            'total' => $produits->count(),
            'produits' => $produits,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $produit = ProduitPharmaceutique::with(['lotsFefo.antenne'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'produit' => $produit,
        ]);
    }
}
