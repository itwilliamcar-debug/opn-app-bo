<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommandeFournisseur extends Model
{
    use HasFactory;

    protected $table = 'commandes_fournisseurs';

    protected $fillable = [
        'demande_id',
        'numero_commande',
        'fournisseur_id',
        'acheteur_id',
        'montant_total_xaf',
        'statut',
        'date_commande',
        'date_livraison_prevue',
        'conditions_paiement',
        'visa_dir_nom',
        'visa_dir_at',
        'visa_appro_nom',
        'visa_appro_at',
        'visa_daf_nom',
        'visa_daf_at',
        'visa_dg_nom',
        'visa_dg_at',
        'signature_hash',
        'qr_token',
        'notes',
    ];

    protected $casts = [
        'date_commande' => 'date',
        'date_livraison_prevue' => 'date',
        'visa_dir_at' => 'datetime',
        'visa_appro_at' => 'datetime',
        'visa_daf_at' => 'datetime',
        'visa_dg_at' => 'datetime',
        'montant_total_xaf' => 'decimal:2',
    ];

    public function fournisseur()
    {
        return $this->belongsTo(Fournisseur::class, 'fournisseur_id');
    }

    public function acheteur()
    {
        return $this->belongsTo(User::class, 'acheteur_id');
    }

    public function lignes()
    {
        return $this->hasMany(LigneCommandeFournisseur::class, 'commande_fournisseur_id');
    }

    public function demande()
    {
        return $this->belongsTo(DemandeApprovisionnement::class, 'demande_id');
    }
}
