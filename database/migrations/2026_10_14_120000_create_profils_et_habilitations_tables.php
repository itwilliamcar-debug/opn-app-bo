<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profils', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('libelle');
            $table->unsignedTinyInteger('niveau_validation')->default(0);
            $table->boolean('systeme')->default(false);
            $table->timestamps();
        });

        Schema::create('habilitations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('libelle');
            $table->string('module', 40);
            $table->timestamps();
        });

        Schema::create('habilitation_profil', function (Blueprint $table) {
            $table->id();
            $table->foreignId('habilitation_id')->constrained('habilitations')->cascadeOnDelete();
            $table->foreignId('profil_id')->constrained('profils')->cascadeOnDelete();
            $table->boolean('creer')->default(false);
            $table->boolean('lire')->default(false);
            $table->boolean('modifier')->default(false);
            $table->boolean('valider')->default(false);
            $table->boolean('supprimer')->default(false);
            $table->timestamps();
            $table->unique(['habilitation_id', 'profil_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('profil_id')->nullable()->after('code_role')->constrained('profils')->nullOnDelete();
            $table->string('etat', 20)->default('actif')->after('profil_id');
            $table->string('poste_comptable', 120)->nullable()->after('etat');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profil_id');
            $table->dropColumn(['etat', 'poste_comptable']);
        });
        Schema::dropIfExists('habilitation_profil');
        Schema::dropIfExists('habilitations');
        Schema::dropIfExists('profils');
    }
};
