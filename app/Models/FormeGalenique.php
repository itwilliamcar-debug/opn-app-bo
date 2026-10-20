<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormeGalenique extends Model
{
    protected $table = 'formes_galeniques';

    protected $fillable = ['code', 'libelle'];

    public function produits(): HasMany
    {
        return $this->hasMany(ProduitPharmaceutique::class, 'forme_id');
    }
}
