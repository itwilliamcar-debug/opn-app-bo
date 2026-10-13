<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreuveLivraison extends Model
{
    use HasFactory;

    protected $table = 'preuves_livraison';

    protected $fillable = [
        'livraison_id',
        'nom_receptionnaire',
        'fonction_receptionnaire',
        'contact_receptionnaire',
        'quantites_recues_json',
        'ecarts_declares',
        'reserves_qualite',
        'produits_endommages',
        'signature_electronique_hash',
        'signature_image_data',
        'date_signature',
        'ip_address',
    ];

    protected $casts = [
        'quantites_recues_json' => 'array',
        'date_signature' => 'datetime',
    ];

    public function livraison()
    {
        return $this->belongsTo(Livraison::class, 'livraison_id');
    }
}
