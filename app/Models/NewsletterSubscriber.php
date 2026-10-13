<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    use HasFactory;

    protected $table = 'newsletter_subscribers';

    protected $fillable = [
        'email',
        'nom',
        'organisation',
        'statut',
        'ip_address',
        'derniere_newsletter_id',
        'derniere_newsletter_date',
    ];

    protected $casts = [
        'derniere_newsletter_date' => 'datetime',
    ];
}
