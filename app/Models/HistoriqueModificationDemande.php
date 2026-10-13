<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoriqueModificationDemande extends Model
{
    use HasFactory;

    protected $table = 'historique_modifications_demande';

    protected $fillable = [
        'demande_id',
        'ligne_id',
        'auteur_nom',
        'role_auteur',
        'champ_modifie',
        'ancienne_valeur',
        'nouvelle_valeur',
        'motif',
        'commentaire',
        'ip_address',
    ];

    public function demande()
    {
        return $this->belongsTo(DemandeApprovisionnement::class, 'demande_id');
    }

    public function ligne()
    {
        return $this->belongsTo(LigneDemande::class, 'ligne_id');
    }
}
