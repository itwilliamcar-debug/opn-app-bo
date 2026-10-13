<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DirectionModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'direction_id',
        'code',
        'nom',
        'description',
        'icone',
        'route',
        'ordre',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    /**
     * Relation avec la direction
     */
    public function direction(): BelongsTo
    {
        return $this->belongsTo(Direction::class);
    }

    /**
     * Relation avec les niveaux d'accès qui ont accès à ce module
     */
    public function niveauxAcces(): BelongsToMany
    {
        return $this->belongsToMany(NiveauAcces::class, 'module_niveau', 'module_id', 'niveau_id');
    }

    /**
     * Scope pour les modules actifs
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
