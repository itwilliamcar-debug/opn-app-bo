<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — GED versionnée + Suggestions au DG National.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appro_documents', function (Blueprint $table) {
            $table->id();
            $table->string('code_document', 40)->unique();
            $table->foreignId('demande_id')->nullable()->constrained('demandes_approvisionnement')->nullOnDelete();
            $table->string('titre');
            $table->string('categorie', 40)->default('AUTRE');
            $table->string('type_mime_attendu', 80)->nullable();
            $table->string('confidentialite', 30)->default('RESTREINT');
            $table->string('statut', 30)->default('ACTIF');
            $table->unsignedInteger('version_courante')->default(1);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motif_archivage')->nullable();
            $table->timestamps();

            $table->index(['demande_id', 'statut']);
            $table->index(['categorie', 'statut']);
        });

        Schema::create('appro_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('appro_documents')->cascadeOnDelete();
            $table->unsignedInteger('numero_version');
            $table->string('nom_fichier_original');
            $table->string('chemin_stockage');
            $table->string('disk', 30)->default('local');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('taille_octets');
            $table->string('checksum_sha256', 64);
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motif_version')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'numero_version']);
            $table->index(['document_id', 'created_at']);
        });

        Schema::create('appro_suggestions_dg', function (Blueprint $table) {
            $table->id();
            $table->string('numero_suggestion', 40)->unique();
            $table->string('titre');
            $table->string('type', 40)->default('RECOMMANDATION');
            $table->text('contenu');
            $table->string('statut', 40)->default('BROUILLON');
            $table->string('priorite', 20)->default('NORMALE');
            $table->foreignId('demande_id')->nullable()->constrained('demandes_approvisionnement')->nullOnDelete();
            $table->foreignId('auteur_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('dg_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dg_decision_at')->nullable();
            $table->text('dg_commentaire')->nullable();
            $table->text('motif_correction')->nullable();
            $table->timestamps();

            $table->index(['statut', 'submitted_at']);
            $table->index(['auteur_user_id', 'statut']);
        });

        Schema::table('appro_documents', function (Blueprint $table) {
            $table->foreignId('suggestion_id')->nullable()->after('demande_id')
                ->constrained('appro_suggestions_dg')->nullOnDelete();
        });

        Schema::create('appro_suggestion_evenements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suggestion_id')->constrained('appro_suggestions_dg')->cascadeOnDelete();
            $table->string('type_evenement', 60);
            $table->string('acteur_nom');
            $table->string('acteur_role', 100)->nullable();
            $table->foreignId('acteur_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('details')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['suggestion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appro_suggestion_evenements');
        Schema::table('appro_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suggestion_id');
        });
        Schema::dropIfExists('appro_suggestions_dg');
        Schema::dropIfExists('appro_document_versions');
        Schema::dropIfExists('appro_documents');
    }
};
