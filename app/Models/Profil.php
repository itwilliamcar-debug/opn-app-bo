<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profil extends Model
{
    protected $table = 'profils';

    protected $fillable = ['code', 'libelle', 'niveau_validation', 'systeme'];

    protected $casts = [
        'systeme' => 'boolean',
        'niveau_validation' => 'integer',
    ];

    public function utilisateurs(): HasMany
    {
        return $this->hasMany(User::class, 'profil_id');
    }

    public function habilitations(): BelongsToMany
    {
        return $this->belongsToMany(Habilitation::class, 'habilitation_profil')
            ->withPivot(['creer', 'lire', 'modifier', 'valider'])
            ->withTimestamps();
    }
}
