<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApproDocumentVersion extends Model
{
    protected $table = 'appro_document_versions';

    protected $fillable = [
        'document_id',
        'numero_version',
        'nom_fichier_original',
        'chemin_stockage',
        'disk',
        'mime_type',
        'taille_octets',
        'checksum_sha256',
        'uploaded_by_user_id',
        'motif_version',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(ApproDocument::class, 'document_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
