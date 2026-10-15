<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('nom');
            $table->string('chef_lieu');
            $table->timestamps();
        });

        Schema::create('villes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained('provinces')->cascadeOnDelete();
            $table->string('nom');
            $table->timestamps();
            $table->unique(['province_id', 'nom']);
        });

        Schema::table('antennes_provinciales', function (Blueprint $table) {
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('ville_id')->nullable()->constrained('villes')->nullOnDelete();
            $table->foreignId('responsable_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        $antennes = DB::table('antennes_provinciales')->get();
        foreach ($antennes as $antenne) {
            $provinceId = DB::table('provinces')->where('nom', $antenne->nom)->value('id');
            if (!$provinceId) {
                $provinceId = DB::table('provinces')->insertGetId([
                    'code' => $antenne->code_antenne,
                    'nom' => $antenne->nom,
                    'chef_lieu' => $antenne->chef_lieu,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            foreach (array_unique(array_filter([$antenne->chef_lieu, $antenne->ville_antenne])) as $nomVille) {
                $existe = DB::table('villes')->where('province_id', $provinceId)->where('nom', $nomVille)->exists();
                if (!$existe) {
                    DB::table('villes')->insert([
                        'province_id' => $provinceId,
                        'nom' => $nomVille,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $villeId = DB::table('villes')->where('province_id', $provinceId)->where('nom', $antenne->ville_antenne)->value('id')
                ?: DB::table('villes')->where('province_id', $provinceId)->where('nom', $antenne->chef_lieu)->value('id');
            $responsableId = DB::table('users')
                ->where('name', $antenne->responsable_nom)
                ->value('id');
            DB::table('antennes_provinciales')->where('id', $antenne->id)->update([
                'province_id' => $provinceId,
                'ville_id' => $villeId,
                'responsable_user_id' => $responsableId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('antennes_provinciales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsable_user_id');
            $table->dropConstrainedForeignId('ville_id');
            $table->dropConstrainedForeignId('province_id');
        });
        Schema::dropIfExists('villes');
        Schema::dropIfExists('provinces');
    }
};
