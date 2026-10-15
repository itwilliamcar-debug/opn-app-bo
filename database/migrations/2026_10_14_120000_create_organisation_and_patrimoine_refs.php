<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sieges', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('nom');
            $table->string('ville')->nullable();
            $table->string('adresse')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('categories_biens', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('libelle');
            $table->timestamps();
        });

        Schema::create('amortissements', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('libelle');
            $table->unsignedInteger('duree_mois');
            $table->string('methode', 30)->default('LINEAIRE');
            $table->timestamps();
        });

        Schema::table('antennes_provinciales', function (Blueprint $table) {
            $table->foreignId('siege_id')->nullable()->after('id')->constrained('sieges')->nullOnDelete();
        });

        Schema::table('directions', function (Blueprint $table) {
            $table->foreignId('siege_id')->nullable()->after('id')->constrained('sieges')->nullOnDelete();
            $table->foreignId('antenne_id')->nullable()->after('siege_id')->constrained('antennes_provinciales')->nullOnDelete();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('nom');
            $table->string('nature', 20)->default('SERVICE');
            $table->foreignId('direction_id')->constrained('directions')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 40)->nullable()->unique();
            $table->string('nom');
            $table->string('fonction')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('siege_id')->nullable()->constrained('sieges')->nullOnDelete();
            $table->foreignId('antenne_id')->nullable()->constrained('antennes_provinciales')->nullOnDelete();
            $table->foreignId('direction_id')->nullable()->constrained('directions')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('biens_patrimoine', function (Blueprint $table) {
            $table->foreignId('categorie_id')->nullable()->after('designation')->constrained('categories_biens')->nullOnDelete();
            $table->foreignId('amortissement_id')->nullable()->after('date_acquisition')->constrained('amortissements')->nullOnDelete();
            $table->foreignId('detenteur_id')->nullable()->after('localisation')->constrained('agents')->nullOnDelete();
            $table->foreignId('siege_id')->nullable()->after('etat')->constrained('sieges')->nullOnDelete();
        });

        $now = now();
        $siegeId = DB::table('sieges')->insertGetId([
            'code' => 'SIEGE-NATIONAL',
            'nom' => 'Siège national de l\'OPN',
            'ville' => 'Owendo / Libreville',
            'adresse' => 'Hub central Owendo',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('antennes_provinciales')->whereNull('siege_id')->update(['siege_id' => $siegeId]);
        DB::table('directions')->whereNull('siege_id')->whereNull('antenne_id')->update(['siege_id' => $siegeId]);

        $categories = [
            ['MEDICAMENT', 'Médicament'],
            ['MOBILIER', 'Mobilier'],
            ['OUTIL', 'Outil'],
            ['EQUIPEMENT', 'Équipement'],
            ['AUTRE', 'Autre'],
        ];
        foreach ($categories as [$code, $libelle]) {
            DB::table('categories_biens')->insert([
                'code' => $code,
                'libelle' => $libelle,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $plans = [
            ['LIN-36', 'Linéaire 3 ans', 36],
            ['LIN-60', 'Linéaire 5 ans', 60],
            ['LIN-120', 'Linéaire 10 ans', 120],
        ];
        foreach ($plans as [$code, $libelle, $mois]) {
            DB::table('amortissements')->insert([
                'code' => $code,
                'libelle' => $libelle,
                'duree_mois' => $mois,
                'methode' => 'LINEAIRE',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $servicesSiege = [
            'DG' => ['DG-CABINET', 'Cabinet du directeur général', 'DEPARTEMENT'],
            'APPRO' => ['APPRO-INSTRUCTION', 'Instruction des commandes', 'SERVICE'],
            'DAF' => ['DAF-BUDGET', 'Budget et engagements', 'SERVICE'],
            'ACHATS' => ['ACHATS-MARCHES', 'Marchés et fournisseurs', 'SERVICE'],
            'LOGISTIQUE' => ['LOG-CONVOIS', 'Convois et distribution', 'SERVICE'],
        ];
        foreach ($servicesSiege as $directionCode => [$code, $nom, $nature]) {
            $directionId = DB::table('directions')->where('code', $directionCode)->value('id');
            if (! $directionId) {
                continue;
            }
            DB::table('services')->insert([
                'code' => $code,
                'nom' => $nom,
                'nature' => $nature,
                'direction_id' => $directionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $antennes = DB::table('antennes_provinciales')->get(['id', 'code_antenne', 'nom', 'siege_id']);
        foreach ($antennes as $antenne) {
            $directionId = DB::table('directions')->insertGetId([
                'code' => 'ANT-'.$antenne->code_antenne,
                'nom' => 'Direction de l\'antenne '.$antenne->nom,
                'description' => 'Direction rattachée à l\'antenne provinciale',
                'couleur' => '#0E2A4F',
                'icone' => 'Building2',
                'ordre' => 100,
                'actif' => true,
                'siege_id' => $antenne->siege_id,
                'antenne_id' => $antenne->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('services')->insert([
                'code' => 'ANT-'.$antenne->code_antenne.'-APP',
                'nom' => 'Service approvisionnement',
                'nature' => 'SERVICE',
                'direction_id' => $directionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleVersDirection = [
            'DG' => 'DG',
            'DIR' => 'DG',
            'DAF' => 'DAF',
            'APPRO_NATIONAL' => 'APPRO',
            'COLLABORATEUR_APPRO' => 'APPRO',
            'ACHATS' => 'ACHATS',
            'PHARMACIEN_CHEF_NATIONAL' => 'QUALITE',
            'GESTIONNAIRE_LOGISTIQUE' => 'LOGISTIQUE',
            'CHEF_CONVOI_SECURITE' => 'LOGISTIQUE',
            'CONVOYEUR' => 'LOGISTIQUE',
            'COORDINATEUR_URGENCES' => 'URGENCES',
            'COMMUNICATION' => 'COMMUNICATION',
        ];

        $users = DB::table('users')->get(['id', 'name', 'role', 'code_role', 'province_id', 'matricule_opn', 'direction_id']);
        foreach ($users as $user) {
            $antenneId = $user->province_id;
            $siegeDeLantenne = $antenneId
                ? DB::table('antennes_provinciales')->where('id', $antenneId)->value('siege_id')
                : $siegeId;
            $directionId = $user->direction_id;
            if (! $directionId && isset($roleVersDirection[$user->code_role])) {
                $directionId = DB::table('directions')->where('code', $roleVersDirection[$user->code_role])->value('id');
            }
            if (! $directionId && $antenneId) {
                $codeAntenne = DB::table('antennes_provinciales')->where('id', $antenneId)->value('code_antenne');
                $directionId = DB::table('directions')->where('code', 'ANT-'.$codeAntenne)->value('id');
            }
            DB::table('agents')->insert([
                'matricule' => $user->matricule_opn,
                'nom' => $user->name,
                'fonction' => $user->role,
                'user_id' => $user->id,
                'siege_id' => $siegeDeLantenne ?: $siegeId,
                'antenne_id' => $antenneId,
                'direction_id' => $directionId,
                'service_id' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $categorieIds = DB::table('categories_biens')->pluck('id', 'code');
        $plan60 = DB::table('amortissements')->where('code', 'LIN-60')->value('id');
        $biens = DB::table('biens_patrimoine')->get();
        foreach ($biens as $bien) {
            $codeCategorie = strtoupper((string) ($bien->categorie ?? 'AUTRE'));
            $categorieId = $categorieIds[$codeCategorie] ?? $categorieIds['AUTRE'];
            $duree = (int) ($bien->duree_amortissement_mois ?? 60);
            $amortissementId = DB::table('amortissements')->where('duree_mois', $duree)->value('id') ?: $plan60;
            $detenteurId = null;
            if (! empty($bien->detenteur_nom)) {
                $detenteurId = DB::table('agents')->where('nom', $bien->detenteur_nom)->value('id');
                if (! $detenteurId) {
                    $detenteurId = DB::table('agents')->insertGetId([
                        'matricule' => null,
                        'nom' => $bien->detenteur_nom,
                        'fonction' => 'Détenteur de bien',
                        'user_id' => $bien->detenteur_user_id,
                        'siege_id' => $siegeId,
                        'antenne_id' => $bien->antenne_id,
                        'direction_id' => null,
                        'service_id' => null,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
            $siegeDuBien = $bien->antenne_id
                ? DB::table('antennes_provinciales')->where('id', $bien->antenne_id)->value('siege_id')
                : $siegeId;
            DB::table('biens_patrimoine')->where('id', $bien->id)->update([
                'categorie_id' => $categorieId,
                'amortissement_id' => $amortissementId,
                'detenteur_id' => $detenteurId,
                'siege_id' => $siegeDuBien,
            ]);
        }

        Schema::table('biens_patrimoine', function (Blueprint $table) {
            $table->dropForeign(['detenteur_user_id']);
            $table->dropColumn(['categorie', 'duree_amortissement_mois', 'detenteur_user_id', 'detenteur_nom']);
        });
    }

    public function down(): void
    {
        Schema::table('biens_patrimoine', function (Blueprint $table) {
            $table->string('categorie', 40)->nullable();
            $table->unsignedInteger('duree_amortissement_mois')->default(60);
            $table->foreignId('detenteur_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('detenteur_nom')->nullable();
            $table->dropConstrainedForeignId('categorie_id');
            $table->dropConstrainedForeignId('amortissement_id');
            $table->dropConstrainedForeignId('detenteur_id');
            $table->dropConstrainedForeignId('siege_id');
        });

        Schema::dropIfExists('agents');
        Schema::dropIfExists('services');

        Schema::table('directions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('antenne_id');
            $table->dropConstrainedForeignId('siege_id');
        });

        Schema::table('antennes_provinciales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('siege_id');
        });

        Schema::dropIfExists('amortissements');
        Schema::dropIfExists('categories_biens');
        Schema::dropIfExists('sieges');
    }
};
