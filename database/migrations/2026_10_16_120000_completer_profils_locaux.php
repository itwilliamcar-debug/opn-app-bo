<?php

use Database\Seeders\ProfilHabilitationSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recrée au déploiement les profils présents en local et absents de la base en ligne.
     * Les profils déjà configurés dans la matrice conservent leurs cases.
     */
    public function up(): void
    {
        if (!Schema::hasTable('profils') || !Schema::hasTable('habilitations') || !Schema::hasTable('habilitation_profil')) {
            return;
        }

        (new ProfilHabilitationSeeder())->run();
    }

    public function down(): void
    {
    }
};
