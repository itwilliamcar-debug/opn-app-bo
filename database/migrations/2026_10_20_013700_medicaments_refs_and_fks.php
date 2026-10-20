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
        Schema::create('categories_medicaments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle', 150);
            $table->timestamps();
        });

        Schema::create('formes_galeniques', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle', 150);
            $table->timestamps();
        });

        Schema::create('unites_gestion', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle', 80);
            $table->timestamps();
        });

        Schema::table('produits_pharmaceutiques', function (Blueprint $table) {
            $table->foreignId('categorie_id')->nullable()->after('categorie')->constrained('categories_medicaments')->nullOnDelete();
            $table->foreignId('forme_id')->nullable()->after('forme')->constrained('formes_galeniques')->nullOnDelete();
            $table->foreignId('unite_gestion_id')->nullable()->after('unite_gestion')->constrained('unites_gestion')->nullOnDelete();
            $table->foreignId('pays_provenance_id')->nullable()->after('laboratoire')->constrained('pays')->nullOnDelete();
        });

        $this->seedFromExisting();
    }

    public function down(): void
    {
        Schema::table('produits_pharmaceutiques', function (Blueprint $table) {
            $table->dropConstrainedForeignId('categorie_id');
            $table->dropConstrainedForeignId('forme_id');
            $table->dropConstrainedForeignId('unite_gestion_id');
            $table->dropConstrainedForeignId('pays_provenance_id');
        });
        Schema::dropIfExists('unites_gestion');
        Schema::dropIfExists('formes_galeniques');
        Schema::dropIfExists('categories_medicaments');
    }

    private function seedFromExisting(): void
    {
        $now = now();

        $categories = DB::table('produits_pharmaceutiques')
            ->whereNotNull('categorie')
            ->where('categorie', '!=', '')
            ->distinct()
            ->pluck('categorie');
        foreach ($categories as $libelle) {
            $code = Str::upper(Str::limit(Str::slug((string) $libelle, '_'), 48, ''));
            if ($code === '') {
                $code = 'CAT_'.substr(md5((string) $libelle), 0, 8);
            }
            DB::table('categories_medicaments')->updateOrInsert(
                ['code' => $code],
                ['libelle' => $libelle, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $formes = DB::table('produits_pharmaceutiques')
            ->whereNotNull('forme')
            ->where('forme', '!=', '')
            ->distinct()
            ->pluck('forme');
        foreach ($formes as $libelle) {
            $code = Str::upper(Str::limit(Str::slug((string) $libelle, '_'), 48, ''));
            if ($code === '') {
                $code = 'FRM_'.substr(md5((string) $libelle), 0, 8);
            }
            DB::table('formes_galeniques')->updateOrInsert(
                ['code' => $code],
                ['libelle' => $libelle, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $unites = DB::table('produits_pharmaceutiques')
            ->whereNotNull('unite_gestion')
            ->where('unite_gestion', '!=', '')
            ->distinct()
            ->pluck('unite_gestion');
        foreach (['Boîte', 'Flacon', 'Unité', 'Plaquette', 'Ampoule', 'Sachet'] as $defaut) {
            $unites->push($defaut);
        }
        foreach ($unites->unique()->filter() as $libelle) {
            $code = Str::upper(Str::limit(Str::slug((string) $libelle, '_'), 48, ''));
            if ($code === '') {
                $code = 'UNT_'.substr(md5((string) $libelle), 0, 8);
            }
            DB::table('unites_gestion')->updateOrInsert(
                ['code' => $code],
                ['libelle' => $libelle, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $catMap = DB::table('categories_medicaments')->pluck('id', 'libelle');
        $formeMap = DB::table('formes_galeniques')->pluck('id', 'libelle');
        $uniteMap = DB::table('unites_gestion')->pluck('id', 'libelle');

        foreach (DB::table('produits_pharmaceutiques')->get(['id', 'categorie', 'forme', 'unite_gestion']) as $row) {
            DB::table('produits_pharmaceutiques')->where('id', $row->id)->update([
                'categorie_id' => $catMap[$row->categorie] ?? null,
                'forme_id' => $formeMap[$row->forme] ?? null,
                'unite_gestion_id' => $uniteMap[$row->unite_gestion] ?? null,
            ]);
        }
    }
};
