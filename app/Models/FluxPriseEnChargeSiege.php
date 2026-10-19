<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FluxPriseEnChargeSiege extends Model
{
    protected $table = 'flux_prises_en_charge_siege';

    protected $fillable = [
        'commande_id',
        'antenne_id',
        'user_id',
        'auteur_nom',
        'auteur_role',
        'quantite_prise',
        'quantite_reliquat',
        'statut_apres',
        'detail',
        'prise_at',
    ];

    protected $casts = [
        'prise_at' => 'datetime',
    ];

    public function commande(): BelongsTo
    {
        return $this->belongsTo(CommandeTerritoriale::class, 'commande_id');
    }
}
