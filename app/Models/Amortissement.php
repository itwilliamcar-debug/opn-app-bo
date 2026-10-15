<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Amortissement extends Model
{
    protected $fillable = ['code', 'libelle', 'duree_mois', 'methode'];

    public function biens(): HasMany
    {
        return $this->hasMany(BienPatrimoine::class, 'amortissement_id');
    }
}
