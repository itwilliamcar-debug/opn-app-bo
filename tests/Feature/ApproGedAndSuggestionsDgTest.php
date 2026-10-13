<?php

namespace Tests\Feature;

use App\Models\AntenneProvinciale;
use App\Models\ApproDocument;
use App\Models\ApproDocumentVersion;
use App\Models\ApproSuggestionDg;
use App\Models\DemandeApprovisionnement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApproGedAndSuggestionsDgTest extends TestCase
{
    use RefreshDatabase;

    protected function makeAppro(): User
    {
        return User::factory()->create([
            'name' => 'Mme Sylvie NTSAME',
            'email' => 's.ntsame@opn.ga',
            'password' => Hash::make('OPN2026@Appro'),
            'code_role' => 'APPRO_NATIONAL',
            'role' => 'Directrice Approvisionnements Nationaux',
            'ville' => 'Libreville',
        ]);
    }

    protected function makeDg(): User
    {
        return User::factory()->create([
            'name' => 'Dr. Jean-Baptiste OBAME',
            'email' => 'jb.obame@opn.ga',
            'password' => Hash::make('OPN2026@Dg'),
            'code_role' => 'DG',
            'role' => 'Directeur Général',
            'ville' => 'Libreville',
        ]);
    }

    protected function makeOutsider(): User
    {
        return User::factory()->create([
            'name' => 'Dr. Jean OBIANG',
            'email' => 'j.obiang@chu.ga',
            'password' => Hash::make('OPN2026@Out'),
            'code_role' => 'DEMANDEUR_STRUCTURE',
            'role' => 'Pharmacien',
            'ville' => 'Libreville',
        ]);
    }

    protected function makeDemande(): DemandeApprovisionnement
    {
        $antenne = AntenneProvinciale::create([
            'code_antenne' => 'EST',
            'nom' => 'Estuaire',
            'chef_lieu' => 'Libreville',
            'ville_antenne' => 'Libreville',
            'responsable_nom' => 'M. Rodrigue MAVOUNGOU',
            'contact_urgence' => '+241 01 00 00 01',
            'lead_time_jours' => 2,
            'mode_transport_principal' => 'Routier',
            'stock_valeur_xaf' => 1000000,
            'statut_stock' => 'NORMAL',
            'couverture_jours' => 45,
            'is_active' => true,
        ]);

        return DemandeApprovisionnement::create([
            'numero_demande' => 'OPN-CMD-2026-950',
            'antenne_id' => $antenne->id,
            'demandeur_nom' => 'Dr. Jean OBIANG',
            'statut' => 'EN_INSTRUCTION_APPRO',
            'etape_index' => 3,
            'montant_total_xaf' => 100000,
            'quantite_totale_demandee' => 10,
            'quantite_totale_validee' => 10,
            'motif' => 'Test GED',
            'historique_workflow' => [],
        ]);
    }

    public function test_ged_upload_version_download_and_outsider_forbidden(): void
    {
        Storage::fake('local');
        $appro = $this->makeAppro();
        $outsider = $this->makeOutsider();
        $demande = $this->makeDemande();

        Sanctum::actingAs($outsider);
        $this->post('/api/v1/supply/documents', [
            'file' => UploadedFile::fake()->create('devis.pdf', 100, 'application/pdf'),
            'titre' => 'Devis Zenufa',
            'demande_id' => $demande->id,
            'categorie' => 'DEVIS',
        ])->assertForbidden();

        Sanctum::actingAs($appro);
        $upload = $this->post('/api/v1/supply/documents', [
            'file' => UploadedFile::fake()->create('devis.pdf', 120, 'application/pdf'),
            'titre' => 'Devis Zenufa — antipaludiques',
            'demande_id' => $demande->id,
            'categorie' => 'DEVIS',
        ])->assertCreated()->json();

        $docId = $upload['document']['id'];
        $this->assertEquals(1, ApproDocument::find($docId)->version_courante);
        $this->assertDatabaseHas('appro_document_versions', [
            'document_id' => $docId,
            'numero_version' => 1,
        ]);

        $this->post("/api/v1/supply/documents/{$docId}/versions", [
            'file' => UploadedFile::fake()->create('devis-v2.pdf', 130, 'application/pdf'),
            'motif_version' => 'Mise a jour prix unitaire fournisseur',
        ])->assertOk();

        $this->assertEquals(2, ApproDocument::find($docId)->version_courante);
        $this->assertEquals(2, ApproDocumentVersion::where('document_id', $docId)->count());

        Sanctum::actingAs($outsider);
        $this->get("/api/v1/supply/documents/{$docId}/download")->assertForbidden();

        Sanctum::actingAs($appro);
        $this->get("/api/v1/supply/documents/{$docId}/download")->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'GED_DOCUMENT_UPLOAD']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'GED_DOCUMENT_VERSION']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'GED_DOCUMENT_DOWNLOAD']);
    }

    public function test_suggestion_dg_full_circuit_with_correction(): void
    {
        $appro = $this->makeAppro();
        $dg = $this->makeDg();
        $outsider = $this->makeOutsider();

        Sanctum::actingAs($outsider);
        $this->postJson('/api/v1/supply/suggestions-dg', [
            'titre' => 'Hors perimetre',
            'contenu' => str_repeat('x', 25),
        ])->assertForbidden();

        Sanctum::actingAs($appro);
        $created = $this->postJson('/api/v1/supply/suggestions-dg', [
            'titre' => 'Renforcer stock antipaludiques Estuaire',
            'type' => 'STOCK',
            'contenu' => 'Proposition de reapprovisionnement strategique pour l antenne Estuaire avant la saison des pluies.',
            'priorite' => 'HAUTE',
            'soumettre' => true,
        ])->assertCreated()->json();

        $id = $created['suggestion']['id'];
        $this->assertEquals('SOUMISE', ApproSuggestionDg::find($id)->statut);

        Sanctum::actingAs($dg);
        $this->getJson('/api/v1/supply/suggestions-dg?inbox=1')
            ->assertOk()
            ->assertJsonPath('count', 1);

        $this->postJson("/api/v1/supply/suggestions-dg/{$id}/decide", [
            'decision' => 'CORRECTION_DEMANDEE',
            'commentaire' => 'Preciser les volumes CMM et le budget estime',
        ])->assertOk();

        $this->assertEquals('CORRECTION_DEMANDEE', ApproSuggestionDg::find($id)->statut);

        Sanctum::actingAs($appro);
        $this->putJson("/api/v1/supply/suggestions-dg/{$id}", [
            'contenu' => 'Volumes CMM : 2 400 boites. Budget estime 10 800 000 XAF. Priorite saison des pluies Estuaire.',
        ])->assertOk();

        $this->assertEquals(2, ApproSuggestionDg::find($id)->version);

        $this->postJson("/api/v1/supply/suggestions-dg/{$id}/submit")->assertOk();
        $this->assertEquals('SOUMISE', ApproSuggestionDg::find($id)->statut);

        Sanctum::actingAs($dg);
        $this->postJson("/api/v1/supply/suggestions-dg/{$id}/decide", [
            'decision' => 'ACCEPTEE',
            'commentaire' => 'Accord de principe — a mettre en oeuvre avec la Direction Appro',
        ])->assertOk();

        $this->postJson("/api/v1/supply/suggestions-dg/{$id}/decide", [
            'decision' => 'MISE_EN_OEUVRE',
            'commentaire' => 'Execution confiee a la Direction Appro',
        ])->assertOk();

        $this->assertEquals('MISE_EN_OEUVRE', ApproSuggestionDg::find($id)->statut);
        $this->assertDatabaseHas('audit_logs', ['action' => 'SUG_DG_ACCEPTEE']);
    }

    public function test_appro_cannot_decide_suggestion(): void
    {
        $appro = $this->makeAppro();
        Sanctum::actingAs($appro);

        $id = $this->postJson('/api/v1/supply/suggestions-dg', [
            'titre' => 'Alerte rupture Ogooue-Maritime',
            'contenu' => 'Risque de rupture sur les solutes de rehydratation a Port-Gentil.',
            'soumettre' => true,
        ])->json('suggestion.id');

        $this->postJson("/api/v1/supply/suggestions-dg/{$id}/decide", [
            'decision' => 'ACCEPTEE',
            'commentaire' => 'Auto-decision interdite',
        ])->assertForbidden();
    }
}
