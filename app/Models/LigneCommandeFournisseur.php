<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LigneCommandeFournisseur extends Model
{
    use HasFactory;

    protected $table = 'lignes_commande_fournisseur';

    protected $fillable = [
        'commande_fournisseur_id',
        'produit_id',
        'quantite_commandee',
        'prix_unitaire_xaf',
        'total_ligne_xaf',
    ];

    protected $casts = [
        'prix_unitaire_xaf' => 'decimal:2',
        'total_ligne_xaf' => 'decimal:2',
    ];

    public function commande()
    {
        return $this->belongsTo(CommandeFournisseur::class, 'commande_fournisseur_id');
    }

    public function produit()
    {
        return $this->belongsTo(ProduitPharmaceutique::class, 'produit_id');
    }
}
