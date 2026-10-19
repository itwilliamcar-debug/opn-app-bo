<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Quantités déjà prises en charge vs reliquat en attente d'arrivage. */
    public function up(): void
    {
        if (!Schema::hasTable('lignes_commande_territoriale')) {
            return;
        }

        Schema::table('lignes_commande_territoriale', function (Blueprint $table) {
            if (!Schema::hasColumn('lignes_commande_territoriale', 'quantite_prise_en_charge')) {
                $table->unsignedInteger('quantite_prise_en_charge')->default(0)->after('quantite_demandee');
            }
            if (!Schema::hasColumn('lignes_commande_territoriale', 'quantite_reliquat')) {
                $table->unsignedInteger('quantite_reliquat')->default(0)->after('quantite_prise_en_charge');
            }
        });

        // Lignes encore chez l'antenne : rien pris → tout est en reliquat.
        DB::table('lignes_commande_territoriale as l')
            ->join('commandes_territoriales as c', 'c.id', '=', 'l.commande_id')
            ->whereIn('c.statut', ['SOUMISE_ANTENNE', 'REJETEE_ANTENNE', 'REJETEE_SIEGE'])
            ->where('l.quantite_prise_en_charge', 0)
            ->update([
                'l.quantite_reliquat' => DB::raw('l.quantite_demandee'),
            ]);

        // Commandes déjà transmises / dispersées avant cette évolution :
        // considérer que tout le demandé avait été pris en charge.
        DB::table('lignes_commande_territoriale as l')
            ->join('commandes_territoriales as c', 'c.id', '=', 'l.commande_id')
            ->whereIn('c.statut', ['TRANSMISE_SIEGE', 'DISPERSEE'])
            ->update([
                'l.quantite_prise_en_charge' => DB::raw('l.quantite_demandee'),
                'l.quantite_reliquat' => 0,
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('lignes_commande_territoriale')) {
            return;
        }

        Schema::table('lignes_commande_territoriale', function (Blueprint $table) {
            if (Schema::hasColumn('lignes_commande_territoriale', 'quantite_reliquat')) {
                $table->dropColumn('quantite_reliquat');
            }
            if (Schema::hasColumn('lignes_commande_territoriale', 'quantite_prise_en_charge')) {
                $table->dropColumn('quantite_prise_en_charge');
            }
        });
    }
};
