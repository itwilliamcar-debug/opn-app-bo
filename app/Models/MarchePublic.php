<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarchePublic extends Model
{
    protected $table = 'marches_publics';

    protected $fillable = [
        'code',
        'reference',
        'titre',
        'categorie',
        'categorie_label',
        'type',
        'type_code',
        'date_publication_label',
        'date_limite_label',
        'budget',
        'caution',
        'frais_dao',
        'statut',
        'statut_label',
        'allotissement',
        'cahier_charges',
        'description',
        'conditions',
        'autorite',
        'publie',
        'afficher_accueil',
        'ordre',
    ];

    protected $casts = [
        'budget' => 'float',
        'allotissement' => 'array',
        'publie' => 'boolean',
        'afficher_accueil' => 'boolean',
    ];
}
