<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fournisseur extends Model
{
    use HasFactory;

    protected $table = 'fournisseurs';

    protected $fillable = [
        'code_fournisseur',
        'nom',
        'pays',
        'type_fournisseur',
        'certifications',
        'delai_moyen_livraison_jours',
        'contact_nom',
        'contact_email',
        'contact_telephone',
        'statut_agrement',
    ];

    public function commandes()
    {
        return $this->hasMany(CommandeFournisseur::class, 'fournisseur_id');
    }
}
