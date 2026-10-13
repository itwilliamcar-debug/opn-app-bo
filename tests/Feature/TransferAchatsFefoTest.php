<?php

namespace Tests\Feature;

use App\Jobs\CheckLotExpirations;
use App\Models\AntenneProvinciale;
use App\Models\CommandeFournisseur;
use App\Models\DemandeApprovisionnement;
use App\Models\LigneDemande;
use App\Models\LotProduit;
use App\Models\ProduitPharmaceutique;
use App\Models\User;
use App\Models\AllocationFefo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferAchatsFefoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function tokenFor(string $email): string
    {
        $user = User::where('email', $email)->firstOrFail();

        return $user->createToken('test')->plainTextToken;
    }

    protected function makeValidatedDemande(float $montant = 1_500_000): DemandeApprovisionnement
    {
        $antenne = AntenneProvinciale::where('code_antenne', 'EST')->firstOrFail();
        $produit = ProduitPharmaceutique::query()->firstOrFail();

        $demande = DemandeApprovisionnement::create([
            'numero_demande' => 'OPN-TEST-FEFO-'.rand(1000, 9999),
            'antenne_id' => $antenne->id,
            'demandeur_nom' => 'Dr. Jean OBIANG',
            'statut' => 'VALIDEE_NATIONALE',
            'etape_index' => 5,
            'montant_total_xaf' => $montant,
            'quantite_totale_demandee' => 50,
            'quantite_totale_validee' => 50,
            'motif' => 'Test Sprint 2 transfer Achats + FEFO',
            'historique_workflow' => [],
        ]);

        LigneDemande::create([
            'demande_id' => $demande->id,
            'produit_id' => $produit->id,
            'quantite_demandee' => 50,
            'quantite_validee' => 50,
            'quantite_disponible' => 100,
            'quantite_refusee' => 0,
            'quantite_reliquat' => 0,
            'prix_unitaire_xaf' => $montant / 50,
            'total_ligne_xaf' => $montant,
        ]);

        return $demande->fresh(['lignes']);
    }

    public function test_transfer_creates_purchase_order_linked_to_demande(): void
    {
        $demande = $this->makeValidatedDemande();
        $token = $this->tokenFor('s.ntsame@opn.ga');

        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/supply/orders/{$demande->id}/transfer-achats");

        $res->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('demandes_approvisionnement', [
            'id' => $demande->id,
            'statut' => 'TRANSMISE_ACHATS',
        ]);

        $cmd = CommandeFournisseur::where('demande_id', $demande->id)->first();
        $this->assertNotNull($cmd);
        $this->assertGreaterThan(0, $cmd->lignes()->count());
        $this->assertEquals('EN_VALIDATION_DIR', $cmd->statut);
        $this->assertArrayHasKey('commande', $res->json());
    }

    public function test_transfer_reserves_fefo_lots_ordered_by_expiry(): void
    {
        $antenne = AntenneProvinciale::where('code_antenne', 'EST')->firstOrFail();
        $produit = ProduitPharmaceutique::query()->firstOrFail();

        LotProduit::where('produit_id', $produit->id)->where('antenne_id', $antenne->id)->delete();

        $lotLate = LotProduit::create([
            'produit_id' => $produit->id,
            'antenne_id' => $antenne->id,
            'numero_lot' => 'LOT-LATE-999',
            'quantite_disponible' => 40,
            'quantite_reservee' => 0,
            'date_fabrication' => now()->subYear(),
            'date_peremption' => now()->addMonths(18),
            'laboratoire_fabricant' => 'Sanofi',
            'statut_fefo' => 'VALIDE',
            'is_quarantine' => false,
        ]);

        $lotSoon = LotProduit::create([
            'produit_id' => $produit->id,
            'antenne_id' => $antenne->id,
            'numero_lot' => 'LOT-SOON-111',
            'quantite_disponible' => 40,
            'quantite_reservee' => 0,
            'date_fabrication' => now()->subMonths(6),
            'date_peremption' => now()->addMonths(2),
            'laboratoire_fabricant' => 'Zenufa',
            'statut_fefo' => 'PRIORITAIRE',
            'is_quarantine' => false,
        ]);

        $demande = $this->makeValidatedDemande(100_000);
        $token = $this->tokenFor('s.ntsame@opn.ga');

        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/supply/orders/{$demande->id}/transfer-achats");

        $res->assertOk();
        $this->assertGreaterThan(0, $res->json('fefo.reserved_total'));

        $firstAlloc = AllocationFefo::where('demande_id', $demande->id)
            ->where('statut', 'RESERVEE')
            ->orderBy('id')
            ->first();

        $this->assertNotNull($firstAlloc);
        $this->assertEquals($lotSoon->id, $firstAlloc->lot_produit_id);

        $lotSoon->refresh();
        $this->assertEquals(0, (int) $lotSoon->quantite_disponible);
        $this->assertEquals(40, (int) $lotSoon->quantite_reservee);
    }

    public function test_unauthorized_user_cannot_transfer(): void
    {
        $demande = $this->makeValidatedDemande();
        $token = $this->tokenFor('audit.interne@opn.ga');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/supply/orders/{$demande->id}/transfer-achats")
            ->assertForbidden();
    }

    public function test_check_lot_expirations_job_flags_lots(): void
    {
        $antenne = AntenneProvinciale::where('code_antenne', 'EST')->firstOrFail();
        $produit = ProduitPharmaceutique::query()->firstOrFail();

        $lot = LotProduit::create([
            'produit_id' => $produit->id,
            'antenne_id' => $antenne->id,
            'numero_lot' => 'LOT-EXP-JOB',
            'quantite_disponible' => 10,
            'quantite_reservee' => 0,
            'date_fabrication' => now()->subYear(),
            'date_peremption' => now()->addDays(30),
            'laboratoire_fabricant' => 'Fresenius',
            'statut_fefo' => 'VALIDE',
            'is_quarantine' => false,
        ]);

        (new CheckLotExpirations(90))->handle();

        $this->assertEquals('ALERTE_PEREMPTION', $lot->fresh()->statut_fefo);
    }
}
