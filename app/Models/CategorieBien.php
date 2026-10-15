<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategorieBien extends Model
{
    protected $table = 'categories_biens';

    protected $fillable = ['code', 'libelle'];

    public function biens(): HasMany
    {
        return $this->hasMany(BienPatrimoine::class, 'categorie_id');
    }
}
