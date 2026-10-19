<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Actualite extends Model
{
    protected $table = 'actualites';

    protected $fillable = [
        'code',
        'titre',
        'date_label',
        'date_publication',
        'tag',
        'tag_color',
        'resume',
        'contenu',
        'mis_en_avant',
        'publie',
        'ordre',
    ];

    protected $casts = [
        'date_publication' => 'date',
        'mis_en_avant' => 'boolean',
        'publie' => 'boolean',
    ];
}
