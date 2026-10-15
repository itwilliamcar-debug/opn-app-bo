<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StructureSanitaire extends Model
{
    use HasFactory;

    protected $table = 'structures_sanitaires';

    protected $fillable = [
        'code_structure',
        'nom',
        'type_structure_id',
        'type_structure',
        'antenne_id',
        'province_id',
        'province_code',
        'departement_id',
        'departement',
        'ville_id',
        'ville',
        'responsable_user_id',
        'responsable_nom',
        'contact_urgence',
        'email',
        'adresse',
        'latitude',
        'longitude',
        'statut',
    ];

    public function antenne()
    {
        return $this->belongsTo(AntenneProvinciale::class, 'antenne_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeStructure::class, 'type_structure_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function departementAdmin(): BelongsTo
    {
        return $this->belongsTo(Departement::class, 'departement_id');
    }

    public function villeRattachee(): BelongsTo
    {
        return $this->belongsTo(Ville::class, 'ville_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function demandes()
    {
        return $this->hasMany(DemandeApprovisionnement::class, 'structure_id');
    }

    public function livraisons()
    {
        return $this->hasMany(Livraison::class, 'structure_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'structure_id');
    }
}
