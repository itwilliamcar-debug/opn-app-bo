<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApproSuggestionEvenement extends Model
{
    protected $table = 'appro_suggestion_evenements';

    protected $fillable = [
        'suggestion_id',
        'type_evenement',
        'acteur_nom',
        'acteur_role',
        'acteur_user_id',
        'details',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(ApproSuggestionDg::class, 'suggestion_id');
    }
}
