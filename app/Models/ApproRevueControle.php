<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApproRevueControle extends Model
{
    protected $table = 'appro_revues_controle';

    protected $fillable = [
        'demande_id',
        'tache_id',
        'numero_revue',
        'type_controle',
        'resultat',
        'constat',
        'recommandation',
        'controleur_user_id',
        'controleur_role',
        'reviewed_at',
        'exige_correction',
        'elements_concernes',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'exige_correction' => 'boolean',
        'elements_concernes' => 'array',
    ];

    public function demande(): BelongsTo
    {
        return $this->belongsTo(DemandeApprovisionnement::class, 'demande_id');
    }

    public function tache(): BelongsTo
    {
        return $this->belongsTo(ApproGouvernanceTache::class, 'tache_id');
    }

    public function controleur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'controleur_user_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(ApproBoucleCorrection::class, 'revue_controle_id');
    }
}
