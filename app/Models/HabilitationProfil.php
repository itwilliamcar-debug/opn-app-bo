<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HabilitationProfil extends Model
{
    protected $table = 'habilitation_profil';

    protected $fillable = [
        'habilitation_id',
        'profil_id',
        'creer',
        'lire',
        'modifier',
        'valider',
        'supprimer',
    ];

    protected $casts = [
        'creer' => 'boolean',
        'lire' => 'boolean',
        'modifier' => 'boolean',
        'valider' => 'boolean',
        'supprimer' => 'boolean',
    ];

    public function profil(): BelongsTo
    {
        return $this->belongsTo(Profil::class);
    }

    public function habilitation(): BelongsTo
    {
        return $this->belongsTo(Habilitation::class);
    }
}
