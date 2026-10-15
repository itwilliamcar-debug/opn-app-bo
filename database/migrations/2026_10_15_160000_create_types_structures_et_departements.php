<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_structures', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('libelle');
            $table->timestamps();
        });

        Schema::create('departements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained('provinces')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('nom');
            $table->timestamps();
            $table->unique(['province_id', 'nom']);
        });

        Schema::table('structures_sanitaires', function (Blueprint $table) {
            $table->foreignId('type_structure_id')->nullable()->constrained('types_structures')->nullOnDelete();
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('departement_id')->nullable()->constrained('departements')->nullOnDelete();
            $table->foreignId('ville_id')->nullable()->constrained('villes')->nullOnDelete();
            $table->foreignId('responsable_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        $types = [
            ['code' => 'HOPITAL_REGIONAL', 'libelle' => 'Hôpital régional'],
            ['code' => 'CLINIQUE', 'libelle' => 'Clinique'],
            ['code' => 'PHARMACIE_OFFICINE', 'libelle' => 'Pharmacie officine'],
            ['code' => 'CENTRE_MEDICAL', 'libelle' => 'Centre médical'],
            ['code' => 'DISPENSAIRE', 'libelle' => 'Dispensaire'],
        ];
        foreach ($types as $type) {
            DB::table('types_structures')->insert([
                'code' => $type['code'],
                'libelle' => $type['libelle'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $structures = DB::table('structures_sanitaires')->get();
        foreach ($structures as $structure) {
            $provinceId = DB::table('antennes_provinciales')->where('id', $structure->antenne_id)->value('province_id');
            $typeId = DB::table('types_structures')->where('code', $structure->type_structure)->value('id');
            if (!$typeId && $structure->type_structure) {
                $typeId = DB::table('types_structures')->insertGetId([
                    'code' => $this->codeUnique('types_structures', $structure->type_structure),
                    'libelle' => str_replace('_', ' ', $structure->type_structure),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $departementId = null;
            $nomDepartement = trim((string) $structure->departement);
            if ($provinceId && $nomDepartement !== '') {
                $departementId = DB::table('departements')->where('province_id', $provinceId)->where('nom', $nomDepartement)->value('id');
                if (!$departementId) {
                    $departementId = DB::table('departements')->insertGetId([
                        'province_id' => $provinceId,
                        'code' => $this->codeUnique('departements', $nomDepartement),
                        'nom' => $nomDepartement,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $villeId = null;
            $nomVille = trim((string) $structure->ville);
            if ($provinceId && $nomVille !== '') {
                $villeId = DB::table('villes')->where('province_id', $provinceId)->where('nom', $nomVille)->value('id');
                if (!$villeId) {
                    $villeId = DB::table('villes')->insertGetId([
                        'province_id' => $provinceId,
                        'nom' => $nomVille,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $responsableId = $structure->responsable_nom
                ? DB::table('users')->where('name', $structure->responsable_nom)->value('id')
                : null;
            $codeProvince = $provinceId ? DB::table('provinces')->where('id', $provinceId)->value('code') : $structure->province_code;

            DB::table('structures_sanitaires')->where('id', $structure->id)->update([
                'type_structure_id' => $typeId,
                'province_id' => $provinceId,
                'province_code' => $codeProvince ?: $structure->province_code,
                'departement_id' => $departementId,
                'ville_id' => $villeId,
                'responsable_user_id' => $responsableId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('structures_sanitaires', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsable_user_id');
            $table->dropConstrainedForeignId('ville_id');
            $table->dropConstrainedForeignId('departement_id');
            $table->dropConstrainedForeignId('province_id');
            $table->dropConstrainedForeignId('type_structure_id');
        });
        Schema::dropIfExists('departements');
        Schema::dropIfExists('types_structures');
    }

    private function codeUnique(string $table, string $nom): string
    {
        $base = Str::upper(Str::slug($nom, '_'));
        $base = $base !== '' ? substr($base, 0, 40) : 'CODE';
        $code = $base;
        $suffixe = 2;
        while (DB::table($table)->where('code', $code)->exists()) {
            $code = substr($base, 0, 36).'_'.$suffixe;
            $suffixe++;
        }

        return $code;
    }
};
