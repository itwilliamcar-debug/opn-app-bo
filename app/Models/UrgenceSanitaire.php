<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrgenceSanitaire extends Model
{
    use HasFactory;

    protected $table = 'urgences_sanitaires';

    protected $fillable = [
        'code_incident',
        'antenne_id',
        'titre',
        'lieu_precis',
        'type_urgence',
        'gravite',
        'statut',
        'declarant_nom',
        'description',
        'equipe_affectee',
        'action_engagee',
        'temps_prise_en_charge_minutes',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'decimal:6',
        'longitude' => 'decimal:6',
        'temps_prise_en_charge_minutes' => 'integer',
    ];

    public function antenne(): BelongsTo
    {
        return $this->belongsTo(AntenneProvinciale::class, 'antenne_id');
    }
}
