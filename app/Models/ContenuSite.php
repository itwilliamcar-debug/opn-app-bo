<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContenuSite extends Model
{
    protected $table = 'contenus_site';

    protected $fillable = [
        'cle',
        'groupe',
        'titre',
        'resume',
        'contenu',
        'meta',
        'ordre',
        'publie',
    ];

    protected $casts = [
        'meta' => 'array',
        'publie' => 'boolean',
    ];
}
