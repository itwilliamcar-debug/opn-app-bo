<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Ajoute le droit de suppression, à côté de créer, lire, modifier et valider. */
    public function up(): void
    {
        if (!Schema::hasTable('habilitation_profil') || Schema::hasColumn('habilitation_profil', 'supprimer')) {
            return;
        }

        Schema::table('habilitation_profil', function (Blueprint $table) {
            $table->boolean('supprimer')->default(false)->after('valider');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('habilitation_profil') || !Schema::hasColumn('habilitation_profil', 'supprimer')) {
            return;
        }

        Schema::table('habilitation_profil', function (Blueprint $table) {
            $table->dropColumn('supprimer');
        });
    }
};
