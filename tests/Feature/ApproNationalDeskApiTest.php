<?php

namespace Tests\Feature;

use App\Models\AntenneProvinciale;
use App\Models\DemandeApprovisionnement;
use App\Models\LigneDemande;
use App\Models\Livraison;
use App\Models\ProduitPharmaceutique;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Bureau Appro national — droits show / FEFO / clôture livraison.
 */
class ApproNationalDeskApiTest extends TestCase
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

    protected function makeAntenneEstuaire(): AntenneProvinciale
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
            'code_produit' => 'OPN-PAL-001',
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
            'numero_demande' => 'OPN-CMD-2026-701',
            'antenne_id' => $antenne->id,
            'demandeur_nom' => 'Dr. Jean OBIANG',
            'statut' => 'VALIDEE_NATIONALE',
            'etape_index' => 5,
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

        return $demande->fresh(['lignes']);
    }

    public function test_appro_national_can_show_supply_order(): void
    {
        $appro = $this->makeApproNational();
        $antenne = $this->makeAntenneEstuaire();
        $demande = $this->makeDemande($antenne);

        Sanctum::actingAs($appro);

        $this->getJson("/api/v1/supply/orders/{$demande->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('demande.id', $demande->id)
            ->assertJsonPath('demande.numero_demande', 'OPN-CMD-2026-701');
    }

    public function test_appro_national_can_post_allocate_fefo_without_403(): void
    {
        $appro = $this->makeApproNational();
        $antenne = $this->makeAntenneEstuaire();
        $demande = $this->makeDemande($antenne);

        Sanctum::actingAs($appro);

        $res = $this->postJson("/api/v1/supply/orders/{$demande->id}/allocate-fefo");

        $this->assertNotEquals(403, $res->status(), 'APPRO_NATIONAL ne doit pas recevoir 403 sur allocate-fefo');
        $this->assertContains($res->status(), [200, 422]);
        $this->assertArrayHasKey('message', $res->json());
    }

    public function test_appro_national_can_close_delivery_and_outsider_is_forbidden(): void
    {
        $appro = $this->makeApproNational();
        $outsider = $this->makeOutsider();
        $antenne = $this->makeAntenneEstuaire();
        $demande = $this->makeDemande($antenne);

        $livraison = Livraison::create([
            'code_livraison' => 'LIV-2026-701-01',
            'demande_id' => $demande->id,
            'statut' => 'EN_ATTENTE_DE_CLOTURE',
            'qr_token' => 'QR-TEST-APPRO-CLOSE-701',
            'token_expires_at' => now()->addDays(7),
            'is_closed' => false,
        ]);

        Sanctum::actingAs($outsider);
        $this->postJson("/api/v1/logistics/deliveries/{$livraison->id}/close", [
            'validateur_nom' => 'Dr. Jean OBIANG',
            'role_validateur' => 'APPRO_NATIONAL',
            'commentaires' => 'Tentative hors périmètre',
        ])->assertForbidden();

        Sanctum::actingAs($appro);
        $this->postJson("/api/v1/logistics/deliveries/{$livraison->id}/close", [
            'validateur_nom' => 'Mme Sylvie NTSAME',
            'role_validateur' => 'APPRO_NATIONAL',
            'commentaires' => 'Clôture nationale conforme — antenne Estuaire',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('livraison.is_closed', true);

        $this->assertDatabaseHas('livraisons', [
            'id' => $livraison->id,
            'statut' => 'CLOTUREE',
            'is_closed' => true,
        ]);
    }
}
