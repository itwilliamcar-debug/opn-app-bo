<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fournisseur extends Model
{
    use HasFactory;

    protected $table = 'fournisseurs';

    protected $fillable = [
        'code_fournisseur',
        'nom',
        'pays_id',
        'type_fournisseur_id',
        'pays',
        'type_fournisseur',
        'certifications',
        'delai_moyen_livraison_jours',
        'contact_nom',
        'contact_email',
        'contact_telephone',
        'statut_agrement',
    ];

    public function paysOrigine(): BelongsTo
    {
        return $this->belongsTo(Pays::class, 'pays_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeFournisseur::class, 'type_fournisseur_id');
    }

    public function commandes()
    {
        return $this->hasMany(CommandeFournisseur::class, 'fournisseur_id');
    }
}
