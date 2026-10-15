<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommandeTerritoriale extends Model
{
    protected $table = 'commandes_territoriales';

    protected $fillable = [
        'numero_commande',
        'antenne_id',
        'demandeur_user_id',
        'demandeur_nom',
        'demandeur_role',
        'partenaire_nom',
        'partenaire_type',
        'statut',
        'motif',
        'montant_total_xaf',
        'transmise_at',
        'transmise_par_nom',
        'dispersee_at',
        'dispersee_par_nom',
        'note_dispersion',
    ];

    protected $casts = [
        'montant_total_xaf' => 'decimal:2',
        'transmise_at' => 'datetime',
        'dispersee_at' => 'datetime',
    ];

    public function antenne(): BelongsTo
    {
        return $this->belongsTo(AntenneProvinciale::class, 'antenne_id');
    }

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demandeur_user_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneCommandeTerritoriale::class, 'commande_id');
    }

    public function journal(): HasMany
    {
        return $this->hasMany(JournalFluxCommande::class, 'commande_id')->orderBy('id');
    }
}
