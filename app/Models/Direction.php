<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Direction extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'nom',
        'description',
        'couleur',
        'icone',
        'ordre',
        'actif',
        'siege_id',
        'antenne_id',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    /**
     * Relation avec les modules de la direction
     */
    public function siege(): BelongsTo
    {
        return $this->belongsTo(Siege::class, 'siege_id');
    }

    public function antenne(): BelongsTo
    {
        return $this->belongsTo(AntenneProvinciale::class, 'antenne_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(ServiceInterne::class, 'direction_id');
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class, 'direction_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(DirectionModule::class);
    }

    /**
     * Relation avec les utilisateurs de la direction
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Scope pour les directions actives
     */
    public function scopeActive($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope pour trier par ordre
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('ordre');
    }
}
