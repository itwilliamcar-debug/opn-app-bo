<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StructureSanitaire extends Model
{
    use HasFactory;

    protected $table = 'structures_sanitaires';

    protected $fillable = [
        'code_structure',
        'nom',
        'type_structure',
        'antenne_id',
        'province_code',
        'departement',
        'ville',
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
