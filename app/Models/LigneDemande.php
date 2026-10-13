<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneDemande extends Model
{
    use HasFactory;

    protected $table = 'lignes_demande';

    protected $fillable = [
        'demande_id',
        'produit_id',
        'quantite_demandee',
        'quantite_disponible',
        'quantite_validee',
        'quantite_refusee',
        'quantite_reliquat',
        'motif_reliquat',
        'commentaire_ligne',
        'quantite_recue',
        'prix_unitaire_xaf',
        'total_ligne_xaf',
        'chaine_froid',
    ];

    protected $casts = [
        'prix_unitaire_xaf' => 'decimal:2',
        'total_ligne_xaf' => 'decimal:2',
        'chaine_froid' => 'boolean',
    ];

    public function demande(): BelongsTo
    {
        return $this->belongsTo(DemandeApprovisionnement::class, 'demande_id');
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(ProduitPharmaceutique::class, 'produit_id');
    }

    public function historiqueModifications()
    {
        return $this->hasMany(HistoriqueModificationDemande::class, 'ligne_id');
    }
}
