<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flux_pieces_commerciales', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // BON_COMMANDE | FACTURE
            $table->string('numero', 40)->unique();
            $table->foreignId('commande_id')->constrained('commandes_territoriales')->cascadeOnDelete();
            $table->unsignedBigInteger('prise_en_charge_id')->nullable()->index();
            $table->foreignId('antenne_id')->nullable()->constrained('antennes_provinciales')->nullOnDelete();
            $table->unsignedBigInteger('demandeur_user_id')->nullable()->index();
            $table->string('demandeur_nom', 160)->nullable();
            $table->string('partenaire_nom', 200)->nullable();
            $table->string('antenne_nom', 160)->nullable();
            $table->string('numero_commande', 40)->nullable();
            $table->unsignedInteger('quantite_prise_totale')->default(0);
            $table->unsignedInteger('quantite_reliquat_totale')->default(0);
            $table->unsignedInteger('quantite_demandee_totale')->default(0);
            $table->decimal('montant_ht_xaf', 14, 2)->default(0);
            $table->decimal('montant_ttc_xaf', 14, 2)->default(0);
            $table->unsignedSmallInteger('annee')->index();
            $table->unsignedTinyInteger('mois')->index();
            $table->string('emitted_by_nom', 160)->nullable();
            $table->timestamp('emitted_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['type', 'annee', 'mois']);
            $table->index(['antenne_id', 'demandeur_user_id']);
        });

        Schema::create('flux_pieces_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piece_id')->constrained('flux_pieces_commerciales')->cascadeOnDelete();
            $table->unsignedBigInteger('produit_id')->nullable()->index();
            $table->string('designation', 200);
            $table->unsignedInteger('quantite_demandee')->default(0);
            $table->unsignedInteger('quantite_prise')->default(0);
            $table->unsignedInteger('quantite_reliquat')->default(0);
            $table->decimal('prix_unitaire_xaf', 14, 2)->default(0);
            $table->decimal('montant_ligne_xaf', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flux_pieces_lignes');
        Schema::dropIfExists('flux_pieces_commerciales');
    }
};
