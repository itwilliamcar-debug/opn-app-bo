<?php

namespace Tests\Feature;

use App\Models\ConnectConversation;
use App\Models\User;
use Database\Seeders\ConnectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConnectPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $dg = User::factory()->create([
            'name' => 'Dr. Jean-Bernard OBAME NDONG',
            'email' => 'jb.obame@opn.ga',
            'password' => Hash::make('OPN2026@Libreville'),
            'code_role' => 'DG',
            'role' => 'Directeur Général',
            'entite' => 'Direction Générale OPN',
        ]);

        User::factory()->create([
            'name' => 'Mme Sylvie NTSAME ONDO',
            'email' => 's.ntsame@opn.ga',
            'password' => Hash::make('OPN2026@Owendo'),
            'code_role' => 'APPRO_NATIONAL',
            'role' => 'Directrice Appro',
            'entite' => 'Appro Owendo',
        ]);

        User::factory()->create([
            'name' => 'Mme Grâce NDZIÉ OBAME',
            'email' => 'g.ndzie@opn.ga',
            'password' => Hash::make('OPN2026@CollabAppro'),
            'code_role' => 'COLLABORATEUR_APPRO',
            'role' => 'Collaboratrice Appro',
            'entite' => 'Appro Owendo',
        ]);

        User::factory()->create([
            'name' => 'M. Jean OBIANG ESSONO',
            'email' => 'j.obiang@opn.ga',
            'password' => Hash::make('OPN2026@CollabAppro2'),
            'code_role' => 'COLLABORATEUR_APPRO',
            'role' => 'Collaborateur Appro',
            'entite' => 'Appro Owendo',
        ]);

        User::factory()->create([
            'name' => 'Inspecteur Martial BOUNDA',
            'email' => 'audit.interne@opn.ga',
            'password' => Hash::make('OPN2026@Audit'),
            'code_role' => 'AUDITEUR_INTERNE',
            'role' => 'Auditeur',
            'entite' => 'Audit',
        ]);

        $this->seed(ConnectSeeder::class);
    }

    public function test_non_member_cannot_list_or_read_private_conversation(): void
    {
        $dg = User::where('email', 'jb.obame@opn.ga')->first();
        $audit = User::where('email', 'audit.interne@opn.ga')->first();
        $conversation = ConnectConversation::query()->first();
        $this->assertNotNull($conversation);

        Sanctum::actingAs($dg);
        $dgList = $this->getJson('/api/v1/connect/conversations');
        $dgList->assertOk();
        $this->assertGreaterThan(0, count($dgList->json('data') ?? []));

        Sanctum::actingAs($audit);
        $auditList = $this->getJson('/api/v1/connect/conversations');
        $auditList->assertOk();
        $auditIds = collect($auditList->json('data') ?? [])->pluck('id');
        $this->assertFalse($auditIds->contains($conversation->id));

        $this->getJson('/api/v1/connect/conversations/'.$conversation->id.'/messages')
            ->assertStatus(403);
    }

    public function test_member_can_send_emoji_message(): void
    {
        $dg = User::where('email', 'jb.obame@opn.ga')->first();
        $conversation = ConnectConversation::query()
            ->whereHas('memberships', fn ($q) => $q->where('user_id', $dg->id))
            ->first();

        Sanctum::actingAs($dg);
        $this->postJson('/api/v1/connect/conversations/'.$conversation->id.'/messages', [
            'type' => 'emoji',
            'emoji_code' => 'gabon',
            'body' => '🇬🇦',
        ])->assertCreated()
            ->assertJsonPath('data.type', 'emoji');
    }
}
