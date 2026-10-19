<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('antennes_provinciales') && ! Schema::hasColumn('antennes_provinciales', 'description')) {
            Schema::table('antennes_provinciales', function (Blueprint $table) {
                $table->text('description')->nullable()->after('couverture_jours');
            });
        }

        if (! Schema::hasTable('actualites')) {
            Schema::create('actualites', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('titre');
                $table->string('date_label', 80);
                $table->date('date_publication')->nullable();
                $table->string('tag', 80);
                $table->string('tag_color', 80)->default('bg-[#2E68B4] text-white');
                $table->text('resume');
                $table->text('contenu');
                $table->boolean('mis_en_avant')->default(false);
                $table->boolean('publie')->default(true);
                $table->unsignedSmallInteger('ordre')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('marches_publics')) {
            Schema::create('marches_publics', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('reference', 120);
                $table->string('titre');
                $table->string('categorie', 40)->default('SERVICES');
                $table->string('categorie_label', 120)->nullable();
                $table->string('type', 120);
                $table->string('type_code', 20)->default('AON');
                $table->string('date_publication_label', 80)->nullable();
                $table->string('date_limite_label', 80);
                $table->decimal('budget', 18, 2)->default(0);
                $table->string('caution', 120)->nullable();
                $table->string('frais_dao', 80)->nullable();
                $table->string('statut', 40)->default('EN_COURS');
                $table->string('statut_label', 80)->default('Soumissions Ouvertes');
                $table->json('allotissement')->nullable();
                $table->string('cahier_charges', 180)->nullable();
                $table->text('description')->nullable();
                $table->text('conditions')->nullable();
                $table->string('autorite', 200)->nullable();
                $table->boolean('publie')->default(true);
                $table->boolean('afficher_accueil')->default(false);
                $table->unsignedSmallInteger('ordre')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('attributions_marches')) {
            Schema::create('attributions_marches', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 120);
                $table->string('objet');
                $table->string('attributaire', 180);
                $table->decimal('montant', 18, 2)->default(0);
                $table->string('date_attribution_label', 80);
                $table->string('delai_execution', 80)->nullable();
                $table->string('statut_dgcpmp', 180)->nullable();
                $table->boolean('publie')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contenus_site')) {
            Schema::create('contenus_site', function (Blueprint $table) {
                $table->id();
                $table->string('cle', 80)->unique();
                $table->string('groupe', 40);
                $table->string('titre')->nullable();
                $table->string('resume', 500)->nullable();
                $table->text('contenu')->nullable();
                $table->json('meta')->nullable();
                $table->unsignedSmallInteger('ordre')->default(0);
                $table->boolean('publie')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contenus_site');
        Schema::dropIfExists('attributions_marches');
        Schema::dropIfExists('marches_publics');
        Schema::dropIfExists('actualites');

        if (Schema::hasTable('antennes_provinciales') && Schema::hasColumn('antennes_provinciales', 'description')) {
            Schema::table('antennes_provinciales', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
};
