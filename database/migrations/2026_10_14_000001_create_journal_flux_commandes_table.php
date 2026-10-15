<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_flux_commandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commande_id')->constrained('commandes_territoriales')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('antenne_id')->nullable()->constrained('antennes_provinciales')->nullOnDelete();
            $table->string('auteur_nom');
            $table->string('auteur_role')->nullable();
            $table->string('action', 40);
            $table->string('statut', 40);
            $table->text('detail')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_flux_commandes');
    }
};
