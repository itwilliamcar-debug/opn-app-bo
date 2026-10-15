<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AntenneProvinciale extends Model
{
    use HasFactory;

    protected $table = 'antennes_provinciales';

    protected $fillable = [
        'siege_id',
        'code_antenne',
        'nom',
        'chef_lieu',
        'ville_antenne',
        'responsable_nom',
        'contact_urgence',
        'lead_time_jours',
        'mode_transport_principal',
        'latitude',
        'longitude',
        'stock_valeur_xaf',
        'statut_stock',
        'couverture_jours',
        'is_active',
    ];

    protected $casts = [
        'stock_valeur_xaf' => 'decimal:2',
        'latitude' => 'decimal:6',
        'longitude' => 'decimal:6',
        'is_active' => 'boolean',
    ];

    public function siege(): BelongsTo
    {
        return $this->belongsTo(Siege::class, 'siege_id');
    }

    public function directions(): HasMany
    {
        return $this->hasMany(Direction::class, 'antenne_id');
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class, 'antenne_id');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(LotProduit::class, 'antenne_id');
    }

    public function demandes(): HasMany
    {
        return $this->hasMany(DemandeApprovisionnement::class, 'antenne_id');
    }

    public function structures(): HasMany
    {
        return $this->hasMany(StructureSanitaire::class, 'antenne_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'province_id');
    }

    public function missionsDepart(): HasMany
    {
        return $this->hasMany(MissionLogistique::class, 'antenne_depart_id');
    }

    public function missionsDestination(): HasMany
    {
        return $this->hasMany(MissionLogistique::class, 'antenne_destination_id');
    }
}
