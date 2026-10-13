<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApproDocument extends Model
{
    protected $table = 'appro_documents';

    protected $fillable = [
        'code_document',
        'demande_id',
        'suggestion_id',
        'titre',
        'categorie',
        'type_mime_attendu',
        'confidentialite',
        'statut',
        'version_courante',
        'created_by_user_id',
        'archived_at',
        'archived_by_user_id',
        'motif_archivage',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    public function demande(): BelongsTo
    {
        return $this->belongsTo(DemandeApprovisionnement::class, 'demande_id');
    }

    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(ApproSuggestionDg::class, 'suggestion_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ApproDocumentVersion::class, 'document_id')->orderByDesc('numero_version');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function versionCourante(): ?ApproDocumentVersion
    {
        return $this->versions()->where('numero_version', $this->version_courante)->first();
    }
}
