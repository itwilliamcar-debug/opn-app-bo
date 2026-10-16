<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute l'antenne et la province géographique de l'utilisateur.
 * province_id reste l'identifiant d'antenne déjà utilisé par le flux.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'antenne_id')) {
                $table->foreignId('antenne_id')->nullable()->after('province_id')->constrained('antennes_provinciales')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'province_rattachement_id')) {
                $table->foreignId('province_rattachement_id')->nullable()->after('antenne_id')->constrained('provinces')->nullOnDelete();
            }
        });

        /* Les comptes existants ont leur antenne dans province_id. On recopie ce lien. */
        $comptes = DB::table('users')->whereNotNull('province_id')->get(['id', 'province_id']);
        foreach ($comptes as $compte) {
            $provinceGeo = DB::table('antennes_provinciales')->where('id', $compte->province_id)->value('province_id');
            DB::table('users')->where('id', $compte->id)->update([
                'antenne_id' => $compte->province_id,
                'province_rattachement_id' => $provinceGeo,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'province_rattachement_id')) {
                $table->dropConstrainedForeignId('province_rattachement_id');
            }
            if (Schema::hasColumn('users', 'antenne_id')) {
                $table->dropConstrainedForeignId('antenne_id');
            }
        });
    }
};
