<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemandeApprovisionnement extends Model
{
    use HasFactory;

    protected $table = 'demandes_approvisionnement';

    protected $fillable = [
        'numero_demande',
        'antenne_id',
        'structure_id',
        'demandeur_nom',
        'statut',
        'etape_index',
        'mode_transport',
        'numero_bordereau',
        'transporteur',
        'chauffeur_ou_agent',
        'montant_total_xaf',
        'quantite_totale_demandee',
        'quantite_totale_validee',
        'quantite_totale_reliquat',
        'jeton_signature_officiel',
        'qr_token_verification',
        'signataire_nom',
        'signataire_role',
        'date_signature_officielle',
        'motif',
        'gouvernance_flag',
        'date_depart_owendo',
        'date_estimee_arrivee',
        'date_reception_effective',
        'historique_workflow',
    ];

    protected $casts = [
        'montant_total_xaf' => 'decimal:2',
        'date_depart_owendo' => 'datetime',
        'date_estimee_arrivee' => 'datetime',
        'date_reception_effective' => 'datetime',
        'date_signature_officielle' => 'datetime',
        'historique_workflow' => 'array',
    ];

    public function antenne(): BelongsTo
    {
        return $this->belongsTo(AntenneProvinciale::class, 'antenne_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(StructureSanitaire::class, 'structure_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneDemande::class, 'demande_id');
    }

    public function livraisons(): HasMany
    {
        return $this->hasMany(Livraison::class, 'demande_id');
    }

    public function historiqueModifications(): HasMany
    {
        return $this->hasMany(HistoriqueModificationDemande::class, 'demande_id');
    }

    public function commandesFournisseur(): HasMany
    {
        return $this->hasMany(CommandeFournisseur::class, 'demande_id');
    }

    public function allocationsFefo(): HasMany
    {
        return $this->hasMany(AllocationFefo::class, 'demande_id');
    }

    public function gouvernanceTaches(): HasMany
    {
        return $this->hasMany(ApproGouvernanceTache::class, 'demande_id');
    }

    public function gouvernanceEvenements(): HasMany
    {
        return $this->hasMany(ApproGouvernanceEvenement::class, 'demande_id');
    }
}
