<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Table des Structures Sanitaires (Hôpitaux, CHR, Cliniques, Pharmacies, Centres de Santé)
        Schema::create('structures_sanitaires', function (Blueprint $table) {
            $table->id();
            $table->string('code_structure', 50)->unique(); // Ex: 'CHR-FRV-01', 'HP-LMB-02'
            $table->string('nom', 200);
            $table->string('type_structure', 50); // 'HOPITAL_REGIONAL', 'CLINIQUE', 'PHARMACIE_OFFICINE', 'CENTRE_MEDICAL', 'DISPENSAIRE'
            $table->foreignId('antenne_id')->constrained('antennes_provinciales')->onDelete('cascade');
            $table->string('province_code', 20)->default('G1');
            $table->string('departement', 150);
            $table->string('ville', 150);
            $table->string('responsable_nom', 150)->nullable();
            $table->string('contact_urgence', 100);
            $table->string('email', 150)->nullable();
            $table->text('adresse')->nullable();
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
            $table->string('statut', 30)->default('ACTIF');
            $table->timestamps();
        });

        // 2. Table des Fournisseurs & Laboratoires Pharmaceutiques Internationaux / Nationaux
        Schema::create('fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->string('code_fournisseur', 50)->unique(); // Ex: 'FOURN-SAN-01'
            $table->string('nom', 200);
            $table->string('pays', 100);
            $table->string('type_fournisseur', 100)->default('LABORATOIRE_FABRICANT'); // 'LABORATOIRE_FABRICANT', 'DISTRIBUTEUR_REGIONAL', 'CENTRALE_ACHAT_PARTENAIRE'
            $table->string('certifications', 200)->default('BPF / OMS / GMP');
            $table->integer('delai_moyen_livraison_jours')->default(30);
            $table->string('contact_nom', 150)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->string('contact_telephone', 100)->nullable();
            $table->string('statut_agrement', 50)->default('AGREE_OPN');
            $table->timestamps();
        });

        // 3. Table des Commandes d'Achats & Marchés Fournisseurs (Module Achats / Procurement)
        Schema::create('commandes_fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->string('numero_commande', 50)->unique(); // Ex: 'CMD-ACHAT-2026-014'
            $table->foreignId('fournisseur_id')->constrained('fournisseurs')->onDelete('cascade');
            $table->foreignId('acheteur_id')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('montant_total_xaf', 15, 2)->default(0);
            $table->string('statut', 50)->default('BROUILLON'); 
            // 'BROUILLON', 'EN_VALIDATION_DIR', 'EN_VALIDATION_APPRO', 'EN_VALIDATION_DAF', 'EN_VALIDATION_DG', 'VALIDE_SIGNE', 'TRANSMIS_LABO', 'EXPEDIE_PORT', 'DEDOUANE_OWENDO', 'RECU_MAGASIN', 'ANNULE'
            $table->date('date_commande');
            $table->date('date_livraison_prevue')->nullable();
            $table->string('conditions_paiement', 150)->nullable();
            $table->string('visa_dir_nom', 150)->nullable();
            $table->timestamp('visa_dir_at')->nullable();
            $table->string('visa_appro_nom', 150)->nullable();
            $table->timestamp('visa_appro_at')->nullable();
            $table->string('visa_daf_nom', 150)->nullable();
            $table->timestamp('visa_daf_at')->nullable();
            $table->string('visa_dg_nom', 150)->nullable();
            $table->timestamp('visa_dg_at')->nullable();
            $table->string('signature_hash', 255)->nullable();
            $table->string('qr_token', 150)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 4. Lignes de Commande Fournisseur
        Schema::create('lignes_commande_fournisseur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commande_fournisseur_id')->constrained('commandes_fournisseurs')->onDelete('cascade');
            $table->foreignId('produit_id')->constrained('produits_pharmaceutiques')->onDelete('cascade');
            $table->integer('quantite_commandee');
            $table->decimal('prix_unitaire_xaf', 12, 2);
            $table->decimal('total_ligne_xaf', 15, 2);
            $table->timestamps();
        });

        // 5. Missions Logistiques & Convois Sécurisés (RN1, RN2, Setrag, Maritime, Aérien)
        Schema::create('missions_logistiques', function (Blueprint $table) {
            $table->id();
            $table->string('code_mission', 50)->unique(); // Ex: 'MIS-2026-042'
            $table->string('ordre_mission_ref', 100)->nullable();
            $table->string('convoi_nom', 200);
            $table->string('type_transport', 100); // 'ROUTIER_RN1', 'ROUTIER_RN2', 'FERROVIAIRE_SETRAG', 'MARITIME_BARGE', 'AERIEN_URGENCE'
            $table->string('vehicule_immat', 100)->nullable();
            $table->string('vehicule_type', 150)->nullable();
            $table->string('chauffeur_ou_agent', 150);
            $table->string('chef_convoi_nom', 150)->nullable();
            $table->foreignId('convoyeur_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('antenne_depart_id')->constrained('antennes_provinciales')->onDelete('cascade');
            $table->foreignId('antenne_destination_id')->constrained('antennes_provinciales')->onDelete('cascade');
            $table->string('statut', 50)->default('PLANIFIEE');
            // 'PLANIFIEE', 'CHARGEMENT_CONFIRME', 'EN_ROUTE', 'ARRIVEE_DESTINATION', 'LIVRAISONS_TERMINEES', 'RAPPORT_SOUMIS', 'CLOTUREE', 'INCIDENT_DECLARE'
            $table->timestamp('date_depart')->nullable();
            $table->timestamp('date_arrivee_estimee')->nullable();
            $table->timestamp('date_arrivee_effective')->nullable();
            $table->decimal('temperature_actuelle', 4, 1)->nullable();
            $table->boolean('temperature_conforme')->default(true);
            $table->integer('kilometrage_depart')->nullable();
            $table->integer('kilometrage_arrivee')->nullable();
            $table->text('incident_description')->nullable();
            $table->text('consignes_securite')->nullable();
            $table->timestamps();
        });

        // 6. Livraisons Rattachées aux Missions et Demandes
        Schema::create('livraisons', function (Blueprint $table) {
            $table->id();
            $table->string('code_livraison', 50)->unique(); // Ex: 'LIV-2026-089-01'
            $table->foreignId('mission_id')->nullable()->constrained('missions_logistiques')->onDelete('set null');
            $table->foreignId('demande_id')->constrained('demandes_approvisionnement')->onDelete('cascade');
            $table->foreignId('structure_id')->nullable()->constrained('structures_sanitaires')->onDelete('set null');
            $table->string('statut', 50)->default('EN_PREPARATION');
            // 'EN_PREPARATION', 'PRETE_A_EXPEDIER', 'EXPEDIEE', 'EN_TRANSIT', 'ARRIVEE', 'EN_RECEPTION', 'RECEPTION_PARTIELLE', 'LIVREE', 'EN_ATTENTE_DE_CLOTURE', 'CLOTUREE', 'ANNULEE'
            $table->string('qr_token', 150)->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('is_qr_scanned')->default(false);
            $table->timestamp('scanned_at')->nullable();
            $table->string('scanned_by_nom', 150)->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        // 7. Preuves de Livraison & Signatures Électroniques Réceptionnaire
        Schema::create('preuves_livraison', function (Blueprint $table) {
            $table->id();
            $table->foreignId('livraison_id')->constrained('livraisons')->onDelete('cascade');
            $table->string('nom_receptionnaire', 150);
            $table->string('fonction_receptionnaire', 150);
            $table->string('contact_receptionnaire', 100)->nullable();
            $table->json('quantites_recues_json');
            $table->text('ecarts_declares')->nullable();
            $table->text('reserves_qualite')->nullable();
            $table->text('produits_endommages')->nullable();
            $table->string('signature_electronique_hash', 255);
            $table->longText('signature_image_data')->nullable(); // Base64 tactile pad
            $table->timestamp('date_signature');
            $table->string('ip_address', 50)->nullable();
            $table->timestamps();
        });

        // 8. Rapports de Mission des Convoyeurs
        Schema::create('rapports_mission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained('missions_logistiques')->onDelete('cascade');
            $table->string('convoyeur_nom', 150);
            $table->string('heure_depart', 20)->nullable();
            $table->string('heure_arrivee', 20)->nullable();
            $table->integer('km_total')->nullable();
            $table->decimal('temperature_min', 4, 1)->nullable();
            $table->decimal('temperature_max', 4, 1)->nullable();
            $table->text('produits_transportes_resume')->nullable();
            $table->text('ecarts_constates')->nullable();
            $table->text('incidents_route')->nullable();
            $table->text('observations')->nullable();
            $table->string('signature_convoyeur', 255)->nullable();
            $table->boolean('valide_logistique')->default(false);
            $table->string('valide_logistique_nom', 150)->nullable();
            $table->timestamp('valide_logistique_at')->nullable();
            $table->boolean('valide_appro')->default(false);
            $table->string('valide_appro_nom', 150)->nullable();
            $table->timestamp('valide_appro_at')->nullable();
            $table->string('statut', 50)->default('SOUMIS'); // 'SOUMIS', 'VALIDE_LOGISTIQUE', 'VALIDE_APPRO', 'CLOTURE'
            $table->timestamps();
        });

        // 9. Historique Non-Destructif des Modifications de Demande
        Schema::create('historique_modifications_demande', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->constrained('demandes_approvisionnement')->onDelete('cascade');
            $table->foreignId('ligne_id')->nullable()->constrained('lignes_demande')->onDelete('set null');
            $table->string('auteur_nom', 150);
            $table->string('role_auteur', 100);
            $table->string('champ_modifie', 100); // Ex: 'quantite_validee', 'statut'
            $table->string('ancienne_valeur', 255);
            $table->string('nouvelle_valeur', 255);
            $table->text('motif'); // Motif obligatoire
            $table->text('commentaire')->nullable();
            $table->string('ip_address', 50)->nullable();
            $table->timestamps();
        });

        // 10. Extensions des Tables Existantes
        Schema::table('demandes_approvisionnement', function (Blueprint $table) {
            $table->foreignId('structure_id')->nullable()->constrained('structures_sanitaires')->onDelete('set null');
            $table->integer('quantite_totale_demandee')->default(0);
            $table->integer('quantite_totale_validee')->default(0);
            $table->integer('quantite_totale_reliquat')->default(0);
            $table->string('jeton_signature_officiel', 255)->nullable();
            $table->string('qr_token_verification', 150)->nullable();
            $table->string('signataire_nom', 150)->nullable();
            $table->string('signataire_role', 100)->nullable();
            $table->timestamp('date_signature_officielle')->nullable();
        });

        Schema::table('lignes_demande', function (Blueprint $table) {
            $table->integer('quantite_disponible')->default(0);
            $table->integer('quantite_refusee')->default(0);
            $table->integer('quantite_reliquat')->default(0);
            $table->text('motif_reliquat')->nullable();
            $table->text('commentaire_ligne')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('structure_id')->nullable()->constrained('structures_sanitaires')->onDelete('set null');
            $table->string('matricule_opn', 50)->nullable();
            $table->string('service', 150)->nullable();
            $table->string('ville', 150)->nullable();
            $table->string('departement', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['structure_id']);
            $table->dropColumn(['structure_id', 'matricule_opn', 'service', 'ville', 'departement']);
        });

        Schema::table('lignes_demande', function (Blueprint $table) {
            $table->dropColumn(['quantite_disponible', 'quantite_refusee', 'quantite_reliquat', 'motif_reliquat', 'commentaire_ligne']);
        });

        Schema::table('demandes_approvisionnement', function (Blueprint $table) {
            $table->dropForeign(['structure_id']);
            $table->dropColumn([
                'structure_id', 
                'quantite_totale_demandee', 
                'quantite_totale_validee', 
                'quantite_totale_reliquat', 
                'jeton_signature_officiel', 
                'qr_token_verification', 
                'signataire_nom', 
                'signataire_role', 
                'date_signature_officielle'
            ]);
        });

        Schema::dropIfExists('historique_modifications_demande');
        Schema::dropIfExists('rapports_mission');
        Schema::dropIfExists('preuves_livraison');
        Schema::dropIfExists('livraisons');
        Schema::dropIfExists('missions_logistiques');
        Schema::dropIfExists('lignes_commande_fournisseur');
        Schema::dropIfExists('commandes_fournisseurs');
        Schema::dropIfExists('fournisseurs');
        Schema::dropIfExists('structures_sanitaires');
    }
};
