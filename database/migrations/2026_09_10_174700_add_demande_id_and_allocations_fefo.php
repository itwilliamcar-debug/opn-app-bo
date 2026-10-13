<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes_fournisseurs', function (Blueprint $table) {
            $table->foreignId('demande_id')
                ->nullable()
                ->after('id')
                ->constrained('demandes_approvisionnement')
                ->nullOnDelete();
        });

        Schema::create('allocations_fefo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->constrained('demandes_approvisionnement')->cascadeOnDelete();
            $table->foreignId('ligne_demande_id')->constrained('lignes_demande')->cascadeOnDelete();
            $table->foreignId('lot_produit_id')->constrained('lots_produits')->cascadeOnDelete();
            $table->unsignedInteger('quantite');
            $table->string('statut', 20)->default('RESERVEE'); // RESERVEE | CONSOMMEE | LIBEREE
            $table->timestamps();

            $table->index(['demande_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocations_fefo');

        Schema::table('commandes_fournisseurs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('demande_id');
        });
    }
};
