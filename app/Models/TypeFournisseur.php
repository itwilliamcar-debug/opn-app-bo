<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Type de fournisseur (laboratoire, distributeur, centrale d'achat).
 * La liste alimente le formulaire Fournisseurs du référentiel.
 */
class TypeFournisseur extends Model
{
    protected $table = 'types_fournisseurs';

    protected $fillable = ['code', 'libelle'];

    public function fournisseurs(): HasMany
    {
        return $this->hasMany(Fournisseur::class, 'type_fournisseur_id');
    }
}
