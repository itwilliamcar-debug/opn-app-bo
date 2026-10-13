<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LotProduit extends Model
{
    use HasFactory;

    protected $table = 'lots_produits';

    protected $fillable = [
        'produit_id',
        'antenne_id',
        'numero_lot',
        'quantite_disponible',
        'quantite_reservee',
        'date_fabrication',
        'date_peremption',
        'laboratoire_fabricant',
        'statut_fefo',
        'emplacement_rack',
        'is_quarantine',
    ];

    protected $casts = [
        'date_fabrication' => 'date',
        'date_peremption' => 'date',
        'is_quarantine' => 'boolean',
    ];

    public function produit(): BelongsTo
    {
        return $this->belongsTo(ProduitPharmaceutique::class, 'produit_id');
    }

    public function antenne(): BelongsTo
    {
        return $this->belongsTo(AntenneProvinciale::class, 'antenne_id');
    }

    /**
     * Nombre de jours avant péremption
     */
    public function getJoursAvantPeremptionAttribute(): int
    {
        return now()->diffInDays($this->date_peremption, false);
    }
}
