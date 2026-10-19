<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialProspect extends Model
{
    protected $table = 'commercial_prospects';

    protected $fillable = [
        'code',
        'antenne_code',
        'antenne_nom',
        'nom_structure',
        'type_structure',
        'province',
        'province_code',
        'localite',
        'region_sanitaire',
        'contact_nom',
        'telephone',
        'email',
        'adresse',
        'gps_lat',
        'gps_lng',
        'photo_url',
        'statut',
        'date_creation',
        'date_derniere_visite',
        'jours_depuis_visite',
        'is_dormant',
        'commercial_assignee',
        'commentaire',
        'devis_associes',
        'client_converti_id',
        'structure_id',
        'user_id',
    ];

    protected $casts = [
        'gps_lat' => 'decimal:7',
        'gps_lng' => 'decimal:7',
        'date_creation' => 'date',
        'date_derniere_visite' => 'date',
        'jours_depuis_visite' => 'integer',
        'is_dormant' => 'boolean',
        'devis_associes' => 'array',
    ];

    public function visites(): HasMany
    {
        return $this->hasMany(CommercialVisite::class, 'prospect_id')->orderByDesc('date_visite');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(StructureSanitaire::class, 'structure_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
