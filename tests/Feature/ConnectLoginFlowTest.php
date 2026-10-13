<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ConnectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConnectLoginFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create([
            'name' => 'Dr. Jean-Bernard OBAME NDONG',
            'email' => 'jb.obame@opn.ga',
            'password' => Hash::make('OPN2026@Libreville'),
            'code_role' => 'DG',
            'role' => 'Directeur Général',
            'entite' => 'DG OPN',
            'is_2fa_enabled' => false,
        ]);

        User::factory()->create([
            'name' => 'Mme Sylvie NTSAME ONDO',
            'email' => 's.ntsame@opn.ga',
            'password' => Hash::make('OPN2026@Owendo'),
            'code_role' => 'APPRO_NATIONAL',
            'role' => 'Directrice Appro',
            'entite' => 'Appro',
            'is_2fa_enabled' => false,
        ]);

        User::factory()->create([
            'name' => 'Mme Grâce NDZIÉ OBAME',
            'email' => 'g.ndzie@opn.ga',
            'password' => Hash::make('OPN2026@CollabAppro'),
            'code_role' => 'COLLABORATEUR_APPRO',
            'is_2fa_enabled' => false,
        ]);

        User::factory()->create([
            'name' => 'M. Jean OBIANG ESSONO',
            'email' => 'j.obiang@opn.ga',
            'password' => Hash::make('OPN2026@CollabAppro2'),
            'code_role' => 'COLLABORATEUR_APPRO',
            'is_2fa_enabled' => false,
        ]);

        $this->seed(ConnectSeeder::class);
    }

    public function test_login_then_list_private_conversations(): void
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'jb.obame@opn.ga',
            'password' => 'OPN2026@Libreville',
        ]);

        $login->assertOk()->assertJsonPath('success', true);
        $token = $login->json('token');
        $this->assertNotEmpty($token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/connect/conversations')
            ->assertOk()
            ->assertJsonPath('success', true);

        $count = count($this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/connect/conversations')
            ->json('data') ?? []);

        $this->assertGreaterThan(0, $count);
    }
}
