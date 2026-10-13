<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NiveauAcces extends Model
{
    use HasFactory;

    protected $fillable = [
        'direction_id',
        'code',
        'nom',
        'description',
        'niveau_hierarchie',
        'permissions',
        'actif',
    ];

    protected $casts = [
        'permissions' => 'array',
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
     * Relation avec les modules accessibles
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(DirectionModule::class, 'module_niveau', 'niveau_id', 'module_id');
    }

    /**
     * Relation avec les utilisateurs ayant ce niveau
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Vérifier si le niveau a une permission spécifique
     */
    public function hasPermission(string $permission): bool
    {
        if (in_array('all', $this->permissions)) {
            return true;
        }
        return in_array($permission, $this->permissions);
    }

    /**
     * Scope pour les niveaux actifs
     */
    public function scopeActive($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope pour trier par niveau hiérarchique
     */
    public function scopeByHierarchy($query)
    {
        return $query->orderBy('niveau_hierarchie');
    }
}
