<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FluxPieceLigne extends Model
{
    protected $table = 'flux_pieces_lignes';

    protected $fillable = [
        'piece_id',
        'produit_id',
        'designation',
        'quantite_demandee',
        'quantite_prise',
        'quantite_reliquat',
        'prix_unitaire_xaf',
        'montant_ligne_xaf',
    ];

    protected $casts = [
        'prix_unitaire_xaf' => 'decimal:2',
        'montant_ligne_xaf' => 'decimal:2',
    ];

    public function piece(): BelongsTo
    {
        return $this->belongsTo(FluxPieceCommerciale::class, 'piece_id');
    }
}
