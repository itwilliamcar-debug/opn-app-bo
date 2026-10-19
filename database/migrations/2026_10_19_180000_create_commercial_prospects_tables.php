<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commercial_prospects')) {
            Schema::create('commercial_prospects', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('antenne_code', 40)->nullable();
                $table->string('antenne_nom')->nullable();
                $table->string('nom_structure');
                $table->string('type_structure', 120)->nullable();
                $table->string('province', 120)->nullable();
                $table->string('province_code', 20)->nullable();
                $table->string('localite', 120)->nullable();
                $table->string('region_sanitaire', 160)->nullable();
                $table->string('contact_nom', 160)->nullable();
                $table->string('telephone', 60)->nullable();
                $table->string('email', 160)->nullable();
                $table->string('adresse')->nullable();
                $table->decimal('gps_lat', 10, 7)->nullable();
                $table->decimal('gps_lng', 10, 7)->nullable();
                $table->string('photo_url', 500)->nullable();
                $table->string('statut', 40)->default('IDENTIFIE');
                $table->date('date_creation')->nullable();
                $table->date('date_derniere_visite')->nullable();
                $table->unsignedInteger('jours_depuis_visite')->default(0);
                $table->boolean('is_dormant')->default(false);
                $table->string('commercial_assignee', 160)->nullable();
                $table->text('commentaire')->nullable();
                $table->json('devis_associes')->nullable();
                $table->string('client_converti_id', 60)->nullable();

                if (Schema::hasTable('structures_sanitaires')) {
                    $table->foreignId('structure_id')
                        ->nullable()
                        ->constrained('structures_sanitaires')
                        ->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('structure_id')->nullable();
                }

                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['antenne_code', 'statut']);
                $table->index('is_dormant');
            });
        }

        if (! Schema::hasTable('commercial_visites')) {
            Schema::create('commercial_visites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('prospect_id')
                    ->constrained('commercial_prospects')
                    ->cascadeOnDelete();
                $table->string('code_visite', 40);
                $table->date('date_visite');
                $table->string('commercial', 160)->nullable();
                $table->string('resultat', 60)->nullable();
                $table->text('commentaire')->nullable();
                $table->string('prochaine_action', 500)->nullable();
                $table->decimal('gps_lat', 10, 7)->nullable();
                $table->decimal('gps_lng', 10, 7)->nullable();
                $table->string('photo_url', 500)->nullable();
                $table->timestamps();

                $table->unique('code_visite');
                $table->index(['prospect_id', 'date_visite']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_visites');
        Schema::dropIfExists('commercial_prospects');
    }
};
