<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'code_role',
        'profil_id',
        'etat',
        'poste_comptable',
        'entite',
        'province_id',
        'antenne_id',
        'province_rattachement_id',
        'structure_id',
        'direction_id',
        'niveau_acces_id',
        'matricule_opn',
        'service',
        'ville',
        'departement',
        'telephone',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_2fa_enabled' => 'boolean',
            'two_factor_expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function province()
    {
        return $this->belongsTo(AntenneProvinciale::class, 'province_id');
    }

    /** Antenne choisie à la création du compte. */
    public function antenne()
    {
        return $this->belongsTo(AntenneProvinciale::class, 'antenne_id');
    }

    /** Province géographique du compte, distincte de l'antenne. */
    public function provinceRattachement()
    {
        return $this->belongsTo(Province::class, 'province_rattachement_id');
    }

    public function structure()
    {
        return $this->belongsTo(StructureSanitaire::class, 'structure_id');
    }

    public function missions()
    {
        return $this->hasMany(MissionLogistique::class, 'convoyeur_id');
    }

    public function connectMemberships()
    {
        return $this->hasMany(ConnectConversationMember::class, 'user_id');
    }

    public function connectMessages()
    {
        return $this->hasMany(ConnectMessage::class, 'user_id');
    }

    public function direction()
    {
        return $this->belongsTo(Direction::class);
    }

    public function profil()
    {
        return $this->belongsTo(Profil::class, 'profil_id');
    }

    public function niveauAcces()
    {
        return $this->belongsTo(NiveauAcces::class);
    }

    /**
     * Vérifier si l'utilisateur a une permission spécifique
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->niveauAcces) {
            return $this->niveauAcces->hasPermission($permission);
        }
        return false;
    }

    /**
     * Obtenir les modules accessibles par l'utilisateur
     */
    public function accessibleModules()
    {
        if ($this->niveauAcces) {
            return $this->niveauAcces->modules()->active()->ordered()->get();
        }
        return collect();
    }
}
