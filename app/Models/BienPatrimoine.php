<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BienPatrimoine extends Model
{
    protected $table = 'biens_patrimoine';

    protected $fillable = [
        'code_bien',
        'designation',
        'categorie',
        'description',
        'valeur_acquisition',
        'date_acquisition',
        'duree_amortissement_mois',
        'etat',
        'antenne_id',
        'localisation',
        'detenteur_user_id',
        'detenteur_nom',
        'date_remise',
        'date_retour',
        'observations',
    ];

    protected $casts = [
        'valeur_acquisition' => 'decimal:2',
        'date_acquisition' => 'date',
        'date_remise' => 'date',
        'date_retour' => 'date',
    ];

    protected $appends = ['amortissement_cumule', 'valeur_nette'];

    public function antenne(): BelongsTo
    {
        return $this->belongsTo(AntenneProvinciale::class, 'antenne_id');
    }

    public function detenteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'detenteur_user_id');
    }

    public function getAmortissementCumuleAttribute(): float
    {
        $achat = (float) $this->valeur_acquisition;
        $mois = (int) $this->duree_amortissement_mois;
        if ($achat <= 0 || $mois <= 0 || !$this->date_acquisition) {
            return 0;
        }
        $ecoules = $this->date_acquisition->diffInMonths(now());
        $ratio = min(1, $ecoules / $mois);

        return round($achat * $ratio, 2);
    }

    public function getValeurNetteAttribute(): float
    {
        return round(max(0, (float) $this->valeur_acquisition - $this->amortissement_cumule), 2);
    }
}
