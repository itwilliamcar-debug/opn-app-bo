<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Livraison extends Model
{
    use HasFactory;

    protected $table = 'livraisons';

    protected $fillable = [
        'code_livraison',
        'mission_id',
        'demande_id',
        'structure_id',
        'statut',
        'qr_token',
        'token_expires_at',
        'is_qr_scanned',
        'scanned_at',
        'scanned_by_nom',
        'is_closed',
        'closed_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'scanned_at' => 'datetime',
        'closed_at' => 'datetime',
        'is_qr_scanned' => 'boolean',
        'is_closed' => 'boolean',
    ];

    public function mission()
    {
        return $this->belongsTo(MissionLogistique::class, 'mission_id');
    }

    public function demande()
    {
        return $this->belongsTo(DemandeApprovisionnement::class, 'demande_id');
    }

    public function structure()
    {
        return $this->belongsTo(StructureSanitaire::class, 'structure_id');
    }

    public function preuve()
    {
        return $this->hasOne(PreuveLivraison::class, 'livraison_id');
    }
}
