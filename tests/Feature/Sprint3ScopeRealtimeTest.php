<?php

namespace Tests\Feature;

use App\Models\AntenneProvinciale;
use App\Models\AuditLog;
use App\Models\UrgenceSanitaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint3ScopeRealtimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function bearer(string $email): array
    {
        $user = User::where('email', $email)->firstOrFail();
        $token = $user->createToken('test')->plainTextToken;

        return [$user, ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json']];
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->getJson('/api/v1/dashboard/national')->assertUnauthorized();
    }

    public function test_dg_sees_national_dashboard(): void
    {
        [, $headers] = $this->bearer('jb.obame@opn.ga');

        $this->withHeaders($headers)
            ->getJson('/api/v1/dashboard/national')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['kpis' => ['valeur_stock_national_xaf', 'alertes_actives'], 'antennes', 'produits_critiques']);
    }

    public function test_chef_antenne_dashboard_scoped_to_province(): void
    {
        [$user, $headers] = $this->bearer('chef.antenne.pog@opn.ga');

        $res = $this->withHeaders($headers)
            ->getJson('/api/v1/dashboard/national')
            ->assertOk();

        $antennes = $res->json('antennes');
        $this->assertNotEmpty($antennes);
        foreach ($antennes as $a) {
            $this->assertEquals($user->province_id, $a['id']);
        }
    }

    public function test_audit_forbidden_for_appro_allowed_for_auditeur(): void
    {
        [$appro] = $this->bearer('s.ntsame@opn.ga');
        $this->actingAs($appro, 'sanctum')
            ->getJson('/api/v1/audit/logs')
            ->assertForbidden();

        [$auditor] = $this->bearer('audit.interne@opn.ga');
        $this->assertEquals('AUDITEUR_INTERNE', $auditor->code_role);
        $this->assertTrue(app(\App\Services\OpnAuthorizationService::class)->can($auditor, 'audit.view'));

        AuditLog::create([
            'auteur_nom' => 'Systeme',
            'role' => 'TEST',
            'action' => 'TEST_AUDIT',
            'details' => 'Sprint 3',
            'ip_address' => '127.0.0.1',
        ]);

        $this->actingAs($auditor, 'sanctum')
            ->getJson('/api/v1/audit/logs')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_urgence_create_and_resolve_with_server_identity(): void
    {
        [, $headers] = $this->bearer('urgences@opn.ga');
        $antenne = AntenneProvinciale::where('code_antenne', 'EST')->firstOrFail();

        $create = $this->withHeaders($headers)
            ->postJson('/api/v1/emergencies', [
                'antenne_id' => $antenne->id,
                'titre' => 'Rupture serums antivenimeux Makokou',
                'lieu_precis' => 'CHR Makokou - Urgences',
                'type_urgence' => 'PHARMACEUTIQUE',
                'gravite' => 'CRITIQUE',
                'description' => 'Stock epuise — besoin reapprovisionnement urgent depuis Owendo.',
            ]);

        $create->assertCreated()->assertJsonPath('success', true);
        $id = $create->json('urgence.id');
        $this->assertNotEmpty($create->json('urgence.declarant_nom'));
        $this->assertStringNotContainsString('Direction Générale', $create->json('urgence.declarant_nom'));

        $this->withHeaders($headers)
            ->putJson("/api/v1/emergencies/{$id}/resolve")
            ->assertOk()
            ->assertJsonPath('urgence.statut', 'RESOLU');
    }

    public function test_chef_antenne_cannot_declare_outside_province(): void
    {
        [$user, $headers] = $this->bearer('chef.antenne.pog@opn.ga');
        $other = AntenneProvinciale::where('id', '!=', $user->province_id)->firstOrFail();

        $this->withHeaders($headers)
            ->postJson('/api/v1/emergencies', [
                'antenne_id' => $other->id,
                'titre' => 'Tentative hors perimetre',
                'lieu_precis' => 'Hors zone',
                'type_urgence' => 'TERRAIN',
                'gravite' => 'MOYENNE',
                'description' => 'Doit etre refusee par scope antenne.',
            ])
            ->assertForbidden();
    }

    public function test_sse_rejects_without_token(): void
    {
        $this->get('/api/v1/realtime/stream')
            ->assertUnauthorized();
    }

    public function test_sse_accepts_query_token(): void
    {
        $user = User::where('email', 'jb.obame@opn.ga')->firstOrFail();
        $token = $user->createToken('sse')->plainTextToken;

        $response = $this->get('/api/v1/realtime/stream?token=' . urlencode($token));
        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));
        $body = method_exists($response, 'streamedContent')
            ? $response->streamedContent()
            : $response->getContent();
        $this->assertStringContainsString('CONNECTED', $body);
    }
}
