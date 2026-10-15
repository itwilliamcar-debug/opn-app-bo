<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Type d'une structure sanitaire (hôpital, pharmacie, dispensaire).
 * La liste alimente le formulaire Structures du référentiel.
 */
class TypeStructure extends Model
{
    protected $table = 'types_structures';

    protected $fillable = ['code', 'libelle'];

    public function structures(): HasMany
    {
        return $this->hasMany(StructureSanitaire::class, 'type_structure_id');
    }
}
