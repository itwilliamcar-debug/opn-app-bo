<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    protected $fillable = ['code', 'nom', 'chef_lieu'];

    public function villes(): HasMany
    {
        return $this->hasMany(Ville::class);
    }

    public function antennes(): HasMany
    {
        return $this->hasMany(AntenneProvinciale::class);
    }
}
