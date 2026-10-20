<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategorieMedicament extends Model
{
    protected $table = 'categories_medicaments';

    protected $fillable = ['code', 'libelle'];

    public function produits(): HasMany
    {
        return $this->hasMany(ProduitPharmaceutique::class, 'categorie_id');
    }
}
