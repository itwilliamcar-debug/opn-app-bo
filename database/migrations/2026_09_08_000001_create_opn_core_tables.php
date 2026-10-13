<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Table des 9 Antennes Provinciales de l'OPN Gabon
        Schema::create('antennes_provinciales', function (Blueprint $table) {
            $table->id();
            $table->string('code_antenne', 20)->unique(); // Ex: 'G2-FRV'
            $table->string('nom', 150);                  // Ex: 'Haut-Ogooué'
            $table->string('chef_lieu', 100);             // Ex: 'Franceville'
            $table->string('ville_antenne', 150);         // Ex: 'Franceville (Quartier Potos)'
            $table->string('responsable_nom', 150);       // Ex: 'M. Brice ELLA MBA'
            $table->string('contact_urgence', 100);       // Téléphone
            $table->integer('lead_time_jours')->default(5); // Délai d'acheminement depuis Owendo
            $table->string('mode_transport_principal', 150); // Setrag / Routier / Maritime
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
            $table->decimal('stock_valeur_xaf', 15, 2)->default(0); // Valorisation en FCFA
            $table->string('statut_stock', 30)->default('NORMAL'); // 'NORMAL', 'VIGILANCE', 'CRITIQUE'
            $table->integer('couverture_jours')->default(45);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Table du Catalogue National des Médicaments (DCI)
        Schema::create('produits_pharmaceutiques', function (Blueprint $table) {
            $table->id();
            $table->string('code_produit', 50)->unique(); // Ex: 'OPN-PAL-001'
            $table->string('designation', 255);          // Ex: 'Artéméther + Luméfantrine 20/120mg'
            $table->string('dci', 255);                  // Ex: 'Artéméther / Luméfantrine'
            $table->string('forme', 150);                // Ex: 'Comprimé dispersible'
            $table->string('dosage', 150);               // Ex: '20mg/120mg'
            $table->string('conditionnement', 150);      // Ex: 'Boîte de 24 comprimés'
            $table->string('categorie', 100);            // Ex: 'Antipaludique'
            $table->string('laboratoire', 150)->nullable();
            $table->string('unite_gestion', 50)->default('Boîte');
            $table->decimal('prix_unitaire_xaf', 12, 2); // Prix d'achat OPN en FCFA
            $table->decimal('prix_public_plafonne_xaf', 12, 2); // Tarif plafonné Gabon
            $table->integer('stock_national')->default(0);
            $table->integer('seuil_min_national')->default(1000);
            $table->integer('seuil_critique_national')->default(500);
            $table->integer('cmm_nationale')->default(1000); // Consommation moyenne mensuelle
            $table->integer('delai_moyen_appro_jours')->default(30);
            $table->string('statut', 40)->default('DISPONIBLE');
            $table->boolean('is_public_catalogue')->default(true);
            $table->timestamps();
        });

        // 3. Table des Lots Physiques & Traçabilité FEFO
        Schema::create('lots_produits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produit_id')->constrained('produits_pharmaceutiques')->onDelete('cascade');
            $table->foreignId('antenne_id')->nullable()->constrained('antennes_provinciales')->onDelete('set null');
            $table->string('numero_lot', 100); // Ex: 'LOT-2025-ACT-884'
            $table->integer('quantite_disponible')->default(0);
            $table->integer('quantite_reservee')->default(0);
            $table->date('date_fabrication')->nullable();
            $table->date('date_peremption');   // Clé du tri FEFO
            $table->string('laboratoire_fabricant', 150)->nullable();
            $table->string('statut_fefo', 40)->default('VALIDE'); // 'PRIORITAIRE', 'VALIDE', 'ALERTE_PEREMPTION'
            $table->string('emplacement_rack', 50)->nullable();
            $table->boolean('is_quarantine')->default(false);
            $table->timestamps();
        });

        // 4. Table des Demandes d'Approvisionnement
        Schema::create('demandes_approvisionnement', function (Blueprint $table) {
            $table->id();
            $table->string('numero_demande', 50)->unique(); // Ex: 'OPN-CMD-2026-089'
            $table->foreignId('antenne_id')->constrained('antennes_provinciales')->onDelete('cascade');
            $table->string('demandeur_nom', 150);
            $table->string('statut', 40)->default('SOUMIS'); // 'BROUILLON', 'SOUMIS', 'VALIDE_DIRECTION', 'EN_PREPARATION', 'EN_EXPEDITION', 'RECU_CONFORME'
            $table->integer('etape_index')->default(1);
            $table->string('mode_transport', 150)->nullable();
            $table->string('numero_bordereau', 100)->nullable(); // Ex: 'BL-SETRAG-2026-4410'
            $table->string('transporteur', 150)->nullable();
            $table->string('chauffeur_ou_agent', 150)->nullable();
            $table->decimal('montant_total_xaf', 15, 2)->default(0);
            $table->text('motif')->nullable();
            $table->timestamp('date_depart_owendo')->nullable();
            $table->timestamp('date_estimee_arrivee')->nullable();
            $table->timestamp('date_reception_effective')->nullable();
            $table->json('historique_workflow')->nullable();
            $table->timestamps();
        });

        // 5. Table des Lignes de Demande
        Schema::create('lignes_demande', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->constrained('demandes_approvisionnement')->onDelete('cascade');
            $table->foreignId('produit_id')->constrained('produits_pharmaceutiques')->onDelete('cascade');
            $table->integer('quantite_demandee');
            $table->integer('quantite_validee')->default(0);
            $table->integer('quantite_recue')->default(0);
            $table->decimal('prix_unitaire_xaf', 12, 2);
            $table->decimal('total_ligne_xaf', 15, 2);
            $table->boolean('chaine_froid')->default(false);
            $table->timestamps();
        });

        // 6. Table du Centre des Urgences Sanitaires (COD Libreville)
        Schema::create('urgences_sanitaires', function (Blueprint $table) {
            $table->id();
            $table->string('code_incident', 50)->unique(); // Ex: 'URG-2026-088'
            $table->foreignId('antenne_id')->constrained('antennes_provinciales')->onDelete('cascade');
            $table->string('titre', 255);
            $table->string('lieu_precis', 255);
            $table->string('type_urgence', 50); // 'PHARMACEUTIQUE', 'LOGISTIQUE', 'TERRAIN'
            $table->string('gravite', 40)->default('CRITIQUE'); // 'INFO', 'VIGILANCE', 'CRITIQUE', 'URGENCE_ABSOLUE'
            $table->string('statut', 40)->default('NOUVEAU');   // 'NOUVEAU', 'PRISE_EN_CHARGE', 'EN_COURS', 'RESOLU'
            $table->string('declarant_nom', 150);
            $table->text('description');
            $table->string('equipe_affectee', 255)->nullable();
            $table->text('action_engagee')->nullable();
            $table->integer('temps_prise_en_charge_minutes')->default(0);
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
            $table->timestamps();
        });

        // 7. Table des Logs d'Audit (Conformité ANINF Gabon)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('auteur_nom', 150);
            $table->string('role', 100);
            $table->string('action', 100); // 'VALIDATION_COMMANDE', 'MODIFICATION_STOCK_FEFO'...
            $table->text('details');
            $table->string('ip_address', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('urgences_sanitaires');
        Schema::dropIfExists('lignes_demande');
        Schema::dropIfExists('demandes_approvisionnement');
        Schema::dropIfExists('lots_produits');
        Schema::dropIfExists('produits_pharmaceutiques');
        Schema::dropIfExists('antennes_provinciales');
    }
};
