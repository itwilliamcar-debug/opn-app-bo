<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApproGouvernanceEvenement extends Model
{
    protected $table = 'appro_gouvernance_evenements';

    protected $fillable = [
        'demande_id',
        'tache_id',
        'type_evenement',
        'acteur_nom',
        'acteur_role',
        'acteur_user_id',
        'details',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function demande(): BelongsTo
    {
        return $this->belongsTo(DemandeApprovisionnement::class, 'demande_id');
    }

    public function tache(): BelongsTo
    {
        return $this->belongsTo(ApproGouvernanceTache::class, 'tache_id');
    }

    public function acteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acteur_user_id');
    }
}
