<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agent extends Model
{
    protected $fillable = [
        'matricule', 'nom', 'fonction', 'user_id', 'siege_id', 'antenne_id', 'direction_id', 'service_id', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function siege(): BelongsTo
    {
        return $this->belongsTo(Siege::class, 'siege_id');
    }

    public function antenne(): BelongsTo
    {
        return $this->belongsTo(AntenneProvinciale::class, 'antenne_id');
    }

    public function direction(): BelongsTo
    {
        return $this->belongsTo(Direction::class, 'direction_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ServiceInterne::class, 'service_id');
    }
}
