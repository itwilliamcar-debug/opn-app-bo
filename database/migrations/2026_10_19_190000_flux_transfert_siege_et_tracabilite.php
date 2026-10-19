<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes_territoriales', function (Blueprint $table) {
            if (!Schema::hasColumn('commandes_territoriales', 'transferee_au_siege_at')) {
                $table->timestamp('transferee_au_siege_at')->nullable()->after('transmise_par_nom');
            }
            if (!Schema::hasColumn('commandes_territoriales', 'transferee_au_siege_par_user_id')) {
                $table->foreignId('transferee_au_siege_par_user_id')->nullable()->after('transferee_au_siege_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('commandes_territoriales', 'transferee_au_siege_par_nom')) {
                $table->string('transferee_au_siege_par_nom', 160)->nullable()->after('transferee_au_siege_par_user_id');
            }
            if (!Schema::hasColumn('commandes_territoriales', 'prise_en_charge_siege_at')) {
                $table->timestamp('prise_en_charge_siege_at')->nullable()->after('transferee_au_siege_par_nom');
            }
            if (!Schema::hasColumn('commandes_territoriales', 'prise_en_charge_siege_par_nom')) {
                $table->string('prise_en_charge_siege_par_nom', 160)->nullable()->after('prise_en_charge_siege_at');
            }
        });

        if (!Schema::hasTable('flux_transferts_siege')) {
            Schema::create('flux_transferts_siege', function (Blueprint $table) {
                $table->id();
                $table->foreignId('commande_id')->constrained('commandes_territoriales')->cascadeOnDelete();
                $table->foreignId('antenne_id')->constrained('antennes_provinciales')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('auteur_nom', 160);
                $table->string('auteur_role', 120)->nullable();
                $table->string('antenne_nom', 160)->nullable();
                $table->unsignedInteger('annee');
                $table->unsignedTinyInteger('mois');
                $table->unsignedInteger('nb_lignes')->default(0);
                $table->unsignedInteger('quantite_totale')->default(0);
                $table->timestamp('transferee_at');
                $table->text('detail')->nullable();
                $table->timestamps();
                $table->index(['antenne_id', 'annee', 'mois']);
                $table->index('transferee_at');
            });
        }

        if (!Schema::hasTable('flux_prises_en_charge_siege')) {
            Schema::create('flux_prises_en_charge_siege', function (Blueprint $table) {
                $table->id();
                $table->foreignId('commande_id')->constrained('commandes_territoriales')->cascadeOnDelete();
                $table->foreignId('antenne_id')->nullable()->constrained('antennes_provinciales')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('auteur_nom', 160);
                $table->string('auteur_role', 120)->nullable();
                $table->unsignedInteger('quantite_prise')->default(0);
                $table->unsignedInteger('quantite_reliquat')->default(0);
                $table->string('statut_apres', 60)->nullable();
                $table->text('detail')->nullable();
                $table->timestamp('prise_at');
                $table->timestamps();
                $table->index(['antenne_id', 'prise_at']);
            });
        }

        // Vue reporting siège : une ligne par commande avec année/mois de transfert.
        DB::statement('DROP VIEW IF EXISTS vue_commandes_siege');
        DB::statement(<<<'SQL'
CREATE VIEW vue_commandes_siege AS
SELECT
    c.id AS commande_id,
    c.numero_commande,
    c.antenne_id,
    a.nom AS antenne_nom,
    a.chef_lieu AS antenne_chef_lieu,
    c.demandeur_user_id,
    c.demandeur_nom,
    c.demandeur_role,
    c.partenaire_nom,
    c.partenaire_type,
    c.statut,
    c.montant_total_xaf,
    c.transferee_au_siege_at,
    c.transferee_au_siege_par_nom,
    c.prise_en_charge_siege_at,
    c.prise_en_charge_siege_par_nom,
    c.dispersee_at,
    c.dispersee_par_nom,
    EXTRACT(YEAR FROM COALESCE(c.transferee_au_siege_at, c.created_at))::int AS annee,
    EXTRACT(MONTH FROM COALESCE(c.transferee_au_siege_at, c.created_at))::int AS mois,
    (
        SELECT COALESCE(SUM(l.quantite_demandee), 0)
        FROM lignes_commande_territoriale l
        WHERE l.commande_id = c.id
    ) AS quantite_demandee,
    (
        SELECT COALESCE(SUM(l.quantite_prise_en_charge), 0)
        FROM lignes_commande_territoriale l
        WHERE l.commande_id = c.id
    ) AS quantite_prise,
    (
        SELECT COALESCE(SUM(GREATEST(l.quantite_demandee - l.quantite_prise_en_charge, 0)), 0)
        FROM lignes_commande_territoriale l
        WHERE l.commande_id = c.id
    ) AS quantite_reliquat,
    (
        SELECT COALESCE(SUM(l.quantite_dispersee), 0)
        FROM lignes_commande_territoriale l
        WHERE l.commande_id = c.id
    ) AS quantite_dispersee
FROM commandes_territoriales c
LEFT JOIN antennes_provinciales a ON a.id = c.antenne_id
WHERE c.statut IN (
    'TRANSFEREE_SIEGE',
    'TRANSMISE_SIEGE',
    'PARTIELLEMENT_ARCHIVEE',
    'PARTIELLEMENT_TRANSMISE',
    'PARTIELLEMENT_DISPERSEE',
    'DISPERSEE',
    'REJETEE_SIEGE'
)
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vue_commandes_siege');
        Schema::dropIfExists('flux_prises_en_charge_siege');
        Schema::dropIfExists('flux_transferts_siege');

        Schema::table('commandes_territoriales', function (Blueprint $table) {
            if (Schema::hasColumn('commandes_territoriales', 'prise_en_charge_siege_par_nom')) {
                $table->dropColumn('prise_en_charge_siege_par_nom');
            }
            if (Schema::hasColumn('commandes_territoriales', 'prise_en_charge_siege_at')) {
                $table->dropColumn('prise_en_charge_siege_at');
            }
            if (Schema::hasColumn('commandes_territoriales', 'transferee_au_siege_par_nom')) {
                $table->dropColumn('transferee_au_siege_par_nom');
            }
            if (Schema::hasColumn('commandes_territoriales', 'transferee_au_siege_par_user_id')) {
                $table->dropConstrainedForeignId('transferee_au_siege_par_user_id');
            }
            if (Schema::hasColumn('commandes_territoriales', 'transferee_au_siege_at')) {
                $table->dropColumn('transferee_au_siege_at');
            }
        });
    }
};
