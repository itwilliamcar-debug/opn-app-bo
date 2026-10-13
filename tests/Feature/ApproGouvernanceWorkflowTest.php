<?php

namespace Tests\Feature;

use App\Models\AntenneProvinciale;
use App\Models\ApproBoucleCorrection;
use App\Models\ApproGouvernanceTache;
use App\Models\DemandeApprovisionnement;
use App\Models\LigneDemande;
use App\Models\ProduitPharmaceutique;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 1 gouvernance Appro — affectation, soumission, 4 yeux, correction, audit.
 */
class ApproGouvernanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function makeApproNational(): User
    {
        return User::factory()->create([
            'name' => 'Mme Sylvie NTSAME',
            'email' => 's.ntsame@opn.ga',
            'password' => Hash::make('OPN2026@ApproNational'),
            'code_role' => 'APPRO_NATIONAL',
            'role' => 'Directrice Approvisionnements Nationaux',
            'entite' => 'Direction Approvisionnement — Owendo',
            'ville' => 'Libreville',
        ]);
    }

    protected function makeCollaborateur(): User
    {
        return User::factory()->create([
            'name' => 'Mme Grâce NDZIÉ OBAME',
            'email' => 'g.ndzie@opn.ga',
            'password' => Hash::make('OPN2026@Collab'),
            'code_role' => 'COLLABORATEUR_APPRO',
            'role' => 'Collaboratrice Approvisionnements',
            'entite' => 'Direction Approvisionnement — Owendo',
            'ville' => 'Libreville',
        ]);
    }

    protected function makeOutsider(): User
    {
        return User::factory()->create([
            'name' => 'Dr. Jean OBIANG',
            'email' => 'j.obiang@hopital-libreville.ga',
            'password' => Hash::make('OPN2026@Demandeur'),
            'code_role' => 'DEMANDEUR_STRUCTURE',
            'role' => 'Pharmacien demandeur',
            'entite' => 'CHU Libreville',
            'ville' => 'Libreville',
        ]);
    }

    protected function makeAntenne(): AntenneProvinciale
    {
        return AntenneProvinciale::create([
            'code_antenne' => 'EST',
            'nom' => 'Estuaire (Libreville/Owendo)',
            'chef_lieu' => 'Libreville',
            'ville_antenne' => 'Libreville / Owendo',
            'responsable_nom' => 'M. Rodrigue MAVOUNGOU',
            'contact_urgence' => '+241 01 00 00 01',
            'lead_time_jours' => 2,
            'mode_transport_principal' => 'Routier urbain',
            'stock_valeur_xaf' => 800000000,
            'statut_stock' => 'NORMAL',
            'couverture_jours' => 60,
            'is_active' => true,
        ]);
    }

    protected function makeDemande(AntenneProvinciale $antenne): DemandeApprovisionnement
    {
        $produit = ProduitPharmaceutique::create([
            'code_produit' => 'OPN-PAL-901',
            'designation' => 'Artéméther + Luméfantrine 20/120mg',
            'dci' => 'Artéméther / Luméfantrine',
            'forme' => 'Comprimé dispersible',
            'dosage' => '20mg/120mg',
            'conditionnement' => 'Boîte de 24 comprimés',
            'categorie' => 'Antipaludique',
            'laboratoire' => 'Zenufa',
            'unite_gestion' => 'Boîte',
            'prix_unitaire_xaf' => 4500,
            'prix_public_plafonne_xaf' => 6000,
            'stock_national' => 0,
            'cmm_nationale' => 1200,
            'statut' => 'DISPONIBLE',
            'is_public_catalogue' => true,
        ]);

        $demande = DemandeApprovisionnement::create([
            'numero_demande' => 'OPN-CMD-2026-901',
            'antenne_id' => $antenne->id,
            'demandeur_nom' => 'Dr. Jean OBIANG',
            'statut' => 'EN_INSTRUCTION_APPRO',
            'etape_index' => 3,
            'montant_total_xaf' => 225000,
            'quantite_totale_demandee' => 50,
            'quantite_totale_validee' => 50,
            'motif' => 'Réassort CMM antenne Estuaire',
            'historique_workflow' => [],
        ]);

        LigneDemande::create([
            'demande_id' => $demande->id,
            'produit_id' => $produit->id,
            'quantite_demandee' => 50,
            'quantite_disponible' => 0,
            'quantite_validee' => 50,
            'quantite_refusee' => 0,
            'quantite_reliquat' => 0,
            'prix_unitaire_xaf' => 4500,
            'total_ligne_xaf' => 225000,
        ]);

        return $demande->fresh();
    }

    public function test_outsider_cannot_access_governance_today(): void
    {
        Sanctum::actingAs($this->makeOutsider());
        $this->getJson('/api/v1/supply/governance/today')->assertForbidden();
    }

    public function test_full_control_correction_recontrol_loop(): void
    {
        $directrice = $this->makeApproNational();
        $collab = $this->makeCollaborateur();
        $demande = $this->makeDemande($this->makeAntenne());

        Sanctum::actingAs($directrice);

        $create = $this->postJson('/api/v1/supply/governance/tasks', [
            'demande_id' => $demande->id,
            'assignee_user_id' => $collab->id,
            'titre' => 'Instruction dossier Estuaire — antipaludiques',
            'instruction' => 'Vérifier CMM et pièces antenne',
            'priorite' => 'HAUTE',
        ])->assertCreated()->json();

        $tacheId = $create['tache']['id'];
        $this->assertDatabaseHas('appro_gouvernance_taches', [
            'id' => $tacheId,
            'statut' => 'A_FAIRE',
            'assignee_user_id' => $collab->id,
        ]);
        $this->assertDatabaseHas('demandes_approvisionnement', [
            'id' => $demande->id,
            'gouvernance_flag' => 'SOUS_CONTROLE',
        ]);

        Sanctum::actingAs($collab);
        $this->postJson("/api/v1/supply/governance/tasks/{$tacheId}/start")->assertOk();
        $this->postJson("/api/v1/supply/governance/tasks/{$tacheId}/submit", [
            'commentaire' => 'Dossier prêt pour contrôle',
        ])->assertOk();

        $this->assertEquals('SOUMISE_CONTROLE', ApproGouvernanceTache::find($tacheId)->statut);

        // Collaborateur : pas de droit review.decide (403 serveur)
        $this->postJson("/api/v1/supply/governance/tasks/{$tacheId}/control", [
            'decision' => 'VALIDE',
            'constat' => 'Tentative de controle hors role',
        ])->assertForbidden();

        Sanctum::actingAs($directrice);
        $this->postJson("/api/v1/supply/governance/tasks/{$tacheId}/control", [
            'decision' => 'CORRECTION',
            'constat' => 'Quantités antenne incohérentes avec CMM',
            'action_attendue' => 'Recalculer et joindre la fiche CMM',
        ])->assertOk();

        $this->assertEquals('CORRECTION_DEMANDEE', ApproGouvernanceTache::find($tacheId)->statut);
        $correction = ApproBoucleCorrection::where('tache_id', $tacheId)->first();
        $this->assertNotNull($correction);
        $this->assertEquals('OUVERTE', $correction->statut);

        Sanctum::actingAs($collab);
        $this->postJson("/api/v1/supply/governance/corrections/{$correction->id}/submit", [
            'correction_appliquee' => 'CMM recalculée — quantité ramenée à 40 boîtes',
        ])->assertOk();

        $this->assertEquals('CORRECTION_SOUMISE', $correction->fresh()->statut);
        $this->assertEquals('SOUMISE_CONTROLE', ApproGouvernanceTache::find($tacheId)->statut);

        Sanctum::actingAs($directrice);
        $this->postJson("/api/v1/supply/governance/tasks/{$tacheId}/control", [
            'decision' => 'VALIDE',
            'constat' => 'Correction conforme — dossier validé pour suite instruction',
        ])->assertOk();

        $this->assertEquals('VALIDEE', ApproGouvernanceTache::find($tacheId)->statut);
        $this->assertEquals('REVISEE_OK', $correction->fresh()->statut);
        $this->assertEquals('RAS', $demande->fresh()->gouvernance_flag);

        $timeline = $this->getJson("/api/v1/supply/governance/dossiers/{$demande->id}/timeline")
            ->assertOk()
            ->json();

        $this->assertNotEmpty($timeline['timeline']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'GOUV_TACHE_CREEE']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'GOUV_CONTROLE_CORRECTION']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'GOUV_CONTROLE_VALIDE']);
    }

    public function test_four_eyes_blocks_self_control(): void
    {
        $directrice = $this->makeApproNational();
        $demande = $this->makeDemande($this->makeAntenne());

        Sanctum::actingAs($directrice);

        $tacheId = $this->postJson('/api/v1/supply/governance/tasks', [
            'demande_id' => $demande->id,
            'assignee_user_id' => $directrice->id,
            'titre' => 'Preparation auto-assignee',
            'priorite' => 'NORMALE',
        ])->assertCreated()->json('tache.id');

        $this->postJson("/api/v1/supply/governance/tasks/{$tacheId}/start")->assertOk();
        $this->postJson("/api/v1/supply/governance/tasks/{$tacheId}/submit")->assertOk();

        $this->postJson("/api/v1/supply/governance/tasks/{$tacheId}/control", [
            'decision' => 'VALIDE',
            'constat' => 'Auto-validation interdite par principe des quatre yeux',
        ])->assertStatus(422)->assertJsonFragment([
            'message' => 'Principe des quatre yeux : vous ne pouvez pas controler une tache que vous avez preparee.',
        ]);
    }

    public function test_collaborateur_cannot_assign_tasks(): void
    {
        $collab = $this->makeCollaborateur();
        $other = $this->makeApproNational();
        $demande = $this->makeDemande($this->makeAntenne());

        Sanctum::actingAs($collab);
        $this->postJson('/api/v1/supply/governance/tasks', [
            'demande_id' => $demande->id,
            'assignee_user_id' => $other->id,
            'titre' => 'Tentative d affectation non autorisee',
        ])->assertForbidden();
    }
}
