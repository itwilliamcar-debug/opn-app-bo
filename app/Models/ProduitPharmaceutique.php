<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProduitPharmaceutique extends Model
{
    use HasFactory;

    protected $table = 'produits_pharmaceutiques';

    protected $fillable = [
        'code_produit',
        'designation',
        'dci',
        'forme',
        'dosage',
        'conditionnement',
        'categorie',
        'laboratoire',
        'unite_gestion',
        'prix_unitaire_xaf',
        'prix_public_plafonne_xaf',
        'stock_national',
        'seuil_min_national',
        'seuil_critique_national',
        'cmm_nationale',
        'delai_moyen_appro_jours',
        'statut',
        'is_public_catalogue',
    ];

    protected $casts = [
        'prix_unitaire_xaf' => 'decimal:2',
        'prix_public_plafonne_xaf' => 'decimal:2',
        'is_public_catalogue' => 'boolean',
    ];

    public function lots(): HasMany
    {
        return $this->hasMany(LotProduit::class, 'produit_id');
    }

    /**
     * Retourne les lots ordonnés selon la règle FEFO (First Expired, First Out)
     */
    public function lotsFefo()
    {
        return $this->hasMany(LotProduit::class, 'produit_id')
                    ->where('is_quarantine', false)
                    ->where('quantite_disponible', '>', 0)
                    ->orderBy('date_peremption', 'asc');
    }
}
