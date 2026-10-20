<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'forme_id',
        'dosage',
        'conditionnement',
        'categorie',
        'categorie_id',
        'laboratoire',
        'pays_provenance_id',
        'unite_gestion',
        'unite_gestion_id',
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

    public function categorieRef(): BelongsTo
    {
        return $this->belongsTo(CategorieMedicament::class, 'categorie_id');
    }

    public function formeRef(): BelongsTo
    {
        return $this->belongsTo(FormeGalenique::class, 'forme_id');
    }

    public function uniteRef(): BelongsTo
    {
        return $this->belongsTo(UniteGestion::class, 'unite_gestion_id');
    }

    public function paysProvenance(): BelongsTo
    {
        return $this->belongsTo(Pays::class, 'pays_provenance_id');
    }

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
