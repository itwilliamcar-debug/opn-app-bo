<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalFluxCommande extends Model
{
    protected $table = 'journal_flux_commandes';

    protected $fillable = [
        'commande_id',
        'user_id',
        'antenne_id',
        'auteur_nom',
        'auteur_role',
        'action',
        'statut',
        'detail',
    ];

    public function commande(): BelongsTo
    {
        return $this->belongsTo(CommandeTerritoriale::class, 'commande_id');
    }

    public function antenne(): BelongsTo
    {
        return $this->belongsTo(AntenneProvinciale::class, 'antenne_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
