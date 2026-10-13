<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllocationFefo extends Model
{
    protected $table = 'allocations_fefo';

    protected $fillable = [
        'demande_id',
        'ligne_demande_id',
        'lot_produit_id',
        'quantite',
        'statut',
    ];

    public function demande(): BelongsTo
    {
        return $this->belongsTo(DemandeApprovisionnement::class, 'demande_id');
    }

    public function ligne(): BelongsTo
    {
        return $this->belongsTo(LigneDemande::class, 'ligne_demande_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(LotProduit::class, 'lot_produit_id');
    }
}
