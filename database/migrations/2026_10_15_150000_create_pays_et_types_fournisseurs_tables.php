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
        Schema::create('pays', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('nom');
            $table->timestamps();
        });

        Schema::create('types_fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('libelle');
            $table->timestamps();
        });

        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->foreignId('pays_id')->nullable()->constrained('pays')->nullOnDelete();
            $table->foreignId('type_fournisseur_id')->nullable()->constrained('types_fournisseurs')->nullOnDelete();
        });

        $typesConnus = [
            ['code' => 'LABORATOIRE_FABRICANT', 'libelle' => 'Laboratoire fabricant'],
            ['code' => 'DISTRIBUTEUR_REGIONAL', 'libelle' => 'Distributeur régional'],
            ['code' => 'CENTRALE_ACHAT_PARTENAIRE', 'libelle' => "Centrale d'achat partenaire"],
        ];
        foreach ($typesConnus as $type) {
            DB::table('types_fournisseurs')->insert([
                'code' => $type['code'],
                'libelle' => $type['libelle'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $fournisseurs = DB::table('fournisseurs')->get();
        foreach ($fournisseurs as $fournisseur) {
            $nomPays = trim((string) $fournisseur->pays);
            $paysId = null;
            if ($nomPays !== '') {
                $paysId = DB::table('pays')->where('nom', $nomPays)->value('id');
                if (!$paysId) {
                    $paysId = DB::table('pays')->insertGetId([
                        'code' => $this->codeUnique('pays', $nomPays),
                        'nom' => $nomPays,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $codeType = trim((string) $fournisseur->type_fournisseur);
            $typeId = $codeType === '' ? null : DB::table('types_fournisseurs')->where('code', $codeType)->value('id');
            if ($codeType !== '' && !$typeId) {
                $typeId = DB::table('types_fournisseurs')->insertGetId([
                    'code' => $this->codeUnique('types_fournisseurs', $codeType),
                    'libelle' => str_replace('_', ' ', $codeType),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('fournisseurs')->where('id', $fournisseur->id)->update([
                'pays_id' => $paysId,
                'type_fournisseur_id' => $typeId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('type_fournisseur_id');
            $table->dropConstrainedForeignId('pays_id');
        });
        Schema::dropIfExists('types_fournisseurs');
        Schema::dropIfExists('pays');
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
