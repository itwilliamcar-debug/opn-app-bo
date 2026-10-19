<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialVisite extends Model
{
    protected $table = 'commercial_visites';

    protected $fillable = [
        'prospect_id',
        'code_visite',
        'date_visite',
        'commercial',
        'resultat',
        'commentaire',
        'prochaine_action',
        'gps_lat',
        'gps_lng',
        'photo_url',
    ];

    protected $casts = [
        'date_visite' => 'date',
        'gps_lat' => 'decimal:7',
        'gps_lng' => 'decimal:7',
    ];

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(CommercialProspect::class, 'prospect_id');
    }
}
