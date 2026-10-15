<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 1 — l'API métier ne doit plus répondre sans Sanctum.
 */
class ApiSanctumHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_supply_orders_require_sanctum(): void
    {
        $this->getJson('/api/v1/supply/orders')
            ->assertUnauthorized();
    }

    public function test_pharmacy_products_require_sanctum(): void
    {
        $this->getJson('/api/v1/pharmacy/produits')
            ->assertUnauthorized();
    }

    public function test_audit_logs_require_sanctum(): void
    {
        $this->getJson('/api/v1/audit/logs')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_supply_orders(): void
    {
        $this->seed();

        $user = User::where('email', 's.ntsame@opn.ga')->firstOrFail();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/supply/orders')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_refresh_replaces_the_current_token(): void
    {
        $user = User::factory()->create([
            'code_role' => 'DG',
            'role' => 'Directeur Général',
            'is_2fa_enabled' => false,
        ]);
        $token = $user->createToken('opn_auth_token')->plainTextToken;

        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/refresh');

        $res->assertOk()->assertJsonPath('success', true);
        $this->assertSame(1, $user->tokens()->count());
        $newToken = $res->json('token');
        $this->assertIsString($newToken);
        $this->assertNotSame($token, $newToken);

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$newToken}")
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_login_does_not_leak_otp_outside_local(): void
    {
        $this->seed();

        // Force non-local for this assertion
        config(['app.env' => 'production']);
        app()->detectEnvironment(fn () => 'production');

        $res = $this->postJson('/api/v1/auth/login', [
            'email' => 'jb.obame@opn.ga',
            'password' => 'OPN2026@Libreville',
        ]);

        $res->assertOk();
        $this->assertArrayNotHasKey('dev_otp_hint', $res->json());
    }
}
