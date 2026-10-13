<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApproGouvernanceTache extends Model
{
    protected $table = 'appro_gouvernance_taches';

    protected $fillable = [
        'demande_id',
        'code_tache',
        'type',
        'titre',
        'description',
        'statut',
        'priorite',
        'assignee_user_id',
        'created_by_user_id',
        'due_at',
        'submitted_at',
        'completed_at',
        'demande_statut_snapshot',
        'instruction',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function demande(): BelongsTo
    {
        return $this->belongsTo(DemandeApprovisionnement::class, 'demande_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function revues(): HasMany
    {
        return $this->hasMany(ApproRevueControle::class, 'tache_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(ApproBoucleCorrection::class, 'tache_id');
    }
}
