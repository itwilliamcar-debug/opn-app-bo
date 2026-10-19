<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FluxTransfertSiege extends Model
{
    protected $table = 'flux_transferts_siege';

    protected $fillable = [
        'commande_id',
        'antenne_id',
        'user_id',
        'auteur_nom',
        'auteur_role',
        'antenne_nom',
        'annee',
        'mois',
        'nb_lignes',
        'quantite_totale',
        'transferee_at',
        'detail',
    ];

    protected $casts = [
        'transferee_at' => 'datetime',
    ];

    public function commande(): BelongsTo
    {
        return $this->belongsTo(CommandeTerritoriale::class, 'commande_id');
    }

    public function antenne(): BelongsTo
    {
        return $this->belongsTo(AntenneProvinciale::class, 'antenne_id');
    }
}
