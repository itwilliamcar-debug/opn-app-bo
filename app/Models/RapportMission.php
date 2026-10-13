<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RapportMission extends Model
{
    use HasFactory;

    protected $table = 'rapports_mission';

    protected $fillable = [
        'mission_id',
        'convoyeur_nom',
        'heure_depart',
        'heure_arrivee',
        'km_total',
        'temperature_min',
        'temperature_max',
        'produits_transportes_resume',
        'ecarts_constates',
        'incidents_route',
        'observations',
        'signature_convoyeur',
        'valide_logistique',
        'valide_logistique_nom',
        'valide_logistique_at',
        'valide_appro',
        'valide_appro_nom',
        'valide_appro_at',
        'statut',
    ];

    protected $casts = [
        'temperature_min' => 'decimal:1',
        'temperature_max' => 'decimal:1',
        'valide_logistique' => 'boolean',
        'valide_logistique_at' => 'datetime',
        'valide_appro' => 'boolean',
        'valide_appro_at' => 'datetime',
    ];

    public function mission()
    {
        return $this->belongsTo(MissionLogistique::class, 'mission_id');
    }
}
