<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApproSuggestionDg extends Model
{
    protected $table = 'appro_suggestions_dg';

    protected $fillable = [
        'numero_suggestion',
        'titre',
        'type',
        'contenu',
        'statut',
        'priorite',
        'demande_id',
        'auteur_user_id',
        'version',
        'submitted_at',
        'dg_user_id',
        'dg_decision_at',
        'dg_commentaire',
        'motif_correction',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'dg_decision_at' => 'datetime',
    ];

    public function demande(): BelongsTo
    {
        return $this->belongsTo(DemandeApprovisionnement::class, 'demande_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_user_id');
    }

    public function dg(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dg_user_id');
    }

    public function evenements(): HasMany
    {
        return $this->hasMany(ApproSuggestionEvenement::class, 'suggestion_id')->orderBy('created_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApproDocument::class, 'suggestion_id');
    }
}
