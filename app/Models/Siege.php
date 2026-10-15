<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Siege extends Model
{
    protected $fillable = ['code', 'nom', 'ville', 'adresse', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function antennes(): HasMany
    {
        return $this->hasMany(AntenneProvinciale::class, 'siege_id');
    }

    public function directions(): HasMany
    {
        return $this->hasMany(Direction::class, 'siege_id')->whereNull('antenne_id');
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class, 'siege_id');
    }
}
