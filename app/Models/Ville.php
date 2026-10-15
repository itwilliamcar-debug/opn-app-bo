<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ville extends Model
{
    protected $fillable = ['province_id', 'nom'];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function antennes(): HasMany
    {
        return $this->hasMany(AntenneProvinciale::class);
    }
}
