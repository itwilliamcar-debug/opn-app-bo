<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MissionLogistique extends Model
{
    use HasFactory;

    protected $table = 'missions_logistiques';

    protected $fillable = [
        'code_mission',
        'ordre_mission_ref',
        'convoi_nom',
        'type_transport',
        'vehicule_immat',
        'vehicule_type',
        'chauffeur_ou_agent',
        'chef_convoi_nom',
        'convoyeur_id',
        'antenne_depart_id',
        'antenne_destination_id',
        'statut',
        'date_depart',
        'date_arrivee_estimee',
        'date_arrivee_effective',
        'temperature_actuelle',
        'temperature_conforme',
        'kilometrage_depart',
        'kilometrage_arrivee',
        'incident_description',
        'consignes_securite',
    ];

    protected $casts = [
        'date_depart' => 'datetime',
        'date_arrivee_estimee' => 'datetime',
        'date_arrivee_effective' => 'datetime',
        'temperature_actuelle' => 'decimal:1',
        'temperature_conforme' => 'boolean',
    ];

    public function convoyeur()
    {
        return $this->belongsTo(User::class, 'convoyeur_id');
    }

    public function antenneDepart()
    {
        return $this->belongsTo(AntenneProvinciale::class, 'antenne_depart_id');
    }

    public function antenneDestination()
    {
        return $this->belongsTo(AntenneProvinciale::class, 'antenne_destination_id');
    }

    public function livraisons()
    {
        return $this->hasMany(Livraison::class, 'mission_id');
    }

    public function rapports()
    {
        return $this->hasMany(RapportMission::class, 'mission_id');
    }
}
