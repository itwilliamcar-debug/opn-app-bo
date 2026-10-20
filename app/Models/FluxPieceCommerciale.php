<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FluxPieceCommerciale extends Model
{
    protected $table = 'flux_pieces_commerciales';

    public const TYPE_BC = 'BON_COMMANDE';
    public const TYPE_FACTURE = 'FACTURE';

    protected $fillable = [
        'type',
        'numero',
        'commande_id',
        'prise_en_charge_id',
        'antenne_id',
        'demandeur_user_id',
        'demandeur_nom',
        'partenaire_nom',
        'antenne_nom',
        'numero_commande',
        'quantite_prise_totale',
        'quantite_reliquat_totale',
        'quantite_demandee_totale',
        'montant_ht_xaf',
        'montant_ttc_xaf',
        'annee',
        'mois',
        'emitted_by_nom',
        'emitted_at',
        'note',
    ];

    protected $casts = [
        'montant_ht_xaf' => 'decimal:2',
        'montant_ttc_xaf' => 'decimal:2',
        'emitted_at' => 'datetime',
    ];

    public function commande(): BelongsTo
    {
        return $this->belongsTo(CommandeTerritoriale::class, 'commande_id');
    }

    public function antenne(): BelongsTo
    {
        return $this->belongsTo(AntenneProvinciale::class, 'antenne_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(FluxPieceLigne::class, 'piece_id');
    }

    public function priseEnCharge(): BelongsTo
    {
        return $this->belongsTo(FluxPriseEnChargeSiege::class, 'prise_en_charge_id');
    }
}
