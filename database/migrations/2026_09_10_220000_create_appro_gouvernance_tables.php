<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Overlay gouvernance Appro (tâches, contrôles, boucles de correction).
 * Ne duplique pas le cycle demande → visa → livraison : FK vers demandes_approvisionnement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demandes_approvisionnement', function (Blueprint $table) {
            if (!Schema::hasColumn('demandes_approvisionnement', 'gouvernance_flag')) {
                $table->string('gouvernance_flag', 40)->nullable()->after('statut');
            }
        });

        Schema::create('appro_gouvernance_taches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->constrained('demandes_approvisionnement')->cascadeOnDelete();
            $table->string('code_tache', 40)->unique();
            $table->string('type', 40)->default('INSTRUCTION');
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('statut', 40)->default('A_FAIRE');
            $table->string('priorite', 20)->default('NORMALE');
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('demande_statut_snapshot', 40)->nullable();
            $table->text('instruction')->nullable();
            $table->timestamps();

            $table->index(['demande_id', 'statut']);
            $table->index(['assignee_user_id', 'statut']);
            $table->index(['statut', 'due_at']);
        });

        Schema::create('appro_revues_controle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->constrained('demandes_approvisionnement')->cascadeOnDelete();
            $table->foreignId('tache_id')->nullable()->constrained('appro_gouvernance_taches')->nullOnDelete();
            $table->string('numero_revue', 40)->unique();
            $table->string('type_controle', 40)->default('DOCUMENTAIRE');
            $table->string('resultat', 40);
            $table->text('constat');
            $table->text('recommandation')->nullable();
            $table->foreignId('controleur_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('controleur_role', 100)->nullable();
            $table->timestamp('reviewed_at');
            $table->boolean('exige_correction')->default(false);
            $table->json('elements_concernes')->nullable();
            $table->timestamps();

            $table->index(['demande_id', 'resultat']);
            $table->index(['tache_id', 'reviewed_at']);
        });

        Schema::create('appro_boucles_correction', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->constrained('demandes_approvisionnement')->cascadeOnDelete();
            $table->foreignId('revue_controle_id')->constrained('appro_revues_controle')->cascadeOnDelete();
            $table->foreignId('tache_id')->nullable()->constrained('appro_gouvernance_taches')->nullOnDelete();
            $table->string('statut', 40)->default('OUVERTE');
            $table->text('motif');
            $table->text('action_attendue')->nullable();
            $table->text('correction_appliquee')->nullable();
            $table->foreignId('corrige_par_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrige_at')->nullable();
            $table->foreignId('valide_par_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_at')->nullable();
            $table->unsignedTinyInteger('iteration')->default(1);
            $table->timestamps();

            $table->unique(['revue_controle_id', 'iteration']);
            $table->index(['demande_id', 'statut']);
            $table->index(['tache_id', 'statut']);
        });

        Schema::create('appro_gouvernance_evenements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->constrained('demandes_approvisionnement')->cascadeOnDelete();
            $table->foreignId('tache_id')->nullable()->constrained('appro_gouvernance_taches')->nullOnDelete();
            $table->string('type_evenement', 60);
            $table->string('acteur_nom');
            $table->string('acteur_role', 100)->nullable();
            $table->foreignId('acteur_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('details')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['demande_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appro_gouvernance_evenements');
        Schema::dropIfExists('appro_boucles_correction');
        Schema::dropIfExists('appro_revues_controle');
        Schema::dropIfExists('appro_gouvernance_taches');

        Schema::table('demandes_approvisionnement', function (Blueprint $table) {
            if (Schema::hasColumn('demandes_approvisionnement', 'gouvernance_flag')) {
                $table->dropColumn('gouvernance_flag');
            }
        });
    }
};
