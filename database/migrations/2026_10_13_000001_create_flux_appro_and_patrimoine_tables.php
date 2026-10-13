<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commandes_territoriales', function (Blueprint $table) {
            $table->id();
            $table->string('numero_commande', 40)->unique();
            $table->foreignId('antenne_id')->constrained('antennes_provinciales')->cascadeOnDelete();
            $table->foreignId('demandeur_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('demandeur_nom');
            $table->string('demandeur_role')->nullable();
            $table->string('partenaire_nom');
            $table->string('partenaire_type', 40)->default('PHARMACIE');
            $table->string('statut', 40)->default('SOUMISE_ANTENNE');
            $table->text('motif')->nullable();
            $table->decimal('montant_total_xaf', 14, 2)->default(0);
            $table->timestamp('transmise_at')->nullable();
            $table->string('transmise_par_nom')->nullable();
            $table->timestamp('dispersee_at')->nullable();
            $table->string('dispersee_par_nom')->nullable();
            $table->text('note_dispersion')->nullable();
            $table->timestamps();
        });

        Schema::create('lignes_commande_territoriale', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commande_id')->constrained('commandes_territoriales')->cascadeOnDelete();
            $table->foreignId('produit_id')->nullable()->constrained('produits_pharmaceutiques')->nullOnDelete();
            $table->string('designation');
            $table->unsignedInteger('quantite_demandee');
            $table->unsignedInteger('quantite_dispersee')->default(0);
            $table->decimal('prix_unitaire_xaf', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('biens_patrimoine', function (Blueprint $table) {
            $table->id();
            $table->string('code_bien', 40)->unique();
            $table->string('designation');
            $table->string('categorie', 40);
            $table->text('description')->nullable();
            $table->decimal('valeur_acquisition', 14, 2)->default(0);
            $table->date('date_acquisition');
            $table->unsignedInteger('duree_amortissement_mois')->default(60);
            $table->string('etat', 30)->default('BON');
            $table->foreignId('antenne_id')->nullable()->constrained('antennes_provinciales')->nullOnDelete();
            $table->string('localisation')->nullable();
            $table->foreignId('detenteur_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('detenteur_nom')->nullable();
            $table->date('date_remise')->nullable();
            $table->date('date_retour')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biens_patrimoine');
        Schema::dropIfExists('lignes_commande_territoriale');
        Schema::dropIfExists('commandes_territoriales');
    }
};
