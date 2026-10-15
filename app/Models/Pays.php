<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pays d'origine d'un fournisseur.
 * La liste alimente le formulaire Fournisseurs du référentiel.
 */
class Pays extends Model
{
    protected $table = 'pays';

    protected $fillable = ['code', 'nom'];

    public function fournisseurs(): HasMany
    {
        return $this->hasMany(Fournisseur::class, 'pays_id');
    }
}
