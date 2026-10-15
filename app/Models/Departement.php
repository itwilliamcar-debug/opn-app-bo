<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Département d'une province gabonaise.
 * La liste du formulaire Structures ne propose que les départements de la province choisie.
 */
class Departement extends Model
{
    protected $table = 'departements';

    protected $fillable = ['code', 'nom', 'province_id'];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function structures(): HasMany
    {
        return $this->hasMany(StructureSanitaire::class, 'departement_id');
    }
}
