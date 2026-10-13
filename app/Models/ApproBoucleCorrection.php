<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApproBoucleCorrection extends Model
{
    protected $table = 'appro_boucles_correction';

    protected $fillable = [
        'demande_id',
        'revue_controle_id',
        'tache_id',
        'statut',
        'motif',
        'action_attendue',
        'correction_appliquee',
        'corrige_par_user_id',
        'corrige_at',
        'valide_par_user_id',
        'valide_at',
        'iteration',
    ];

    protected $casts = [
        'corrige_at' => 'datetime',
        'valide_at' => 'datetime',
    ];

    public function demande(): BelongsTo
    {
        return $this->belongsTo(DemandeApprovisionnement::class, 'demande_id');
    }

    public function revue(): BelongsTo
    {
        return $this->belongsTo(ApproRevueControle::class, 'revue_controle_id');
    }

    public function tache(): BelongsTo
    {
        return $this->belongsTo(ApproGouvernanceTache::class, 'tache_id');
    }

    public function corrigePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrige_par_user_id');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par_user_id');
    }
}
