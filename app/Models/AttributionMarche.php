<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributionMarche extends Model
{
    protected $table = 'attributions_marches';

    protected $fillable = [
        'reference',
        'objet',
        'attributaire',
        'montant',
        'date_attribution_label',
        'delai_execution',
        'statut_dgcpmp',
        'publie',
    ];

    protected $casts = [
        'montant' => 'float',
        'publie' => 'boolean',
    ];
}
