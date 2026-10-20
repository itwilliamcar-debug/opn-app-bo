<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UniteGestion extends Model
{
    protected $table = 'unites_gestion';

    protected $fillable = ['code', 'libelle'];

    public function produits(): HasMany
    {
        return $this->hasMany(ProduitPharmaceutique::class, 'unite_gestion_id');
    }
}
