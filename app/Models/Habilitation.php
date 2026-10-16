<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Habilitation extends Model
{
    protected $table = 'habilitations';

    protected $fillable = ['code', 'libelle', 'module'];

    public function profils(): BelongsToMany
    {
        return $this->belongsToMany(Profil::class, 'habilitation_profil')
            ->withPivot(['creer', 'lire', 'modifier', 'valider', 'supprimer'])
            ->withTimestamps();
    }
}
