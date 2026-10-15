<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceInterne extends Model
{
    protected $table = 'services';

    protected $fillable = ['code', 'nom', 'nature', 'direction_id'];

    public function direction(): BelongsTo
    {
        return $this->belongsTo(Direction::class, 'direction_id');
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class, 'service_id');
    }
}
