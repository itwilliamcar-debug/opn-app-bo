<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PresenceProvinceScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_list_is_scoped_to_same_province_only(): void
    {
        $pog = User::factory()->create([
            'name' => 'M. Marc ANGUILET',
            'email' => 'chef.antenne.pog@opn.ga',
            'password' => Hash::make('OPN2026@PortGentil'),
            'code_role' => 'CHEF_ANTENNE_PROVINCIALE',
            'province_id' => 8,
            'ville' => 'Port-Gentil',
            'last_seen_at' => now(),
        ]);

        $pogColleague = User::factory()->create([
            'name' => 'Mme Claire MOUSSAVOU',
            'email' => 'pharmacien.pog@opn.ga',
            'password' => Hash::make('OPN2026@PharmaciePog'),
            'code_role' => 'DEMANDEUR_PHARMACIEN',
            'province_id' => 8,
            'ville' => 'Port-Gentil',
            'last_seen_at' => now(),
        ]);

        User::factory()->create([
            'name' => 'Dr. Estelle MANFOUMBI',
            'email' => 'pharmacie.frv@opn.ga',
            'password' => Hash::make('OPN2026@PharmacieFrv'),
            'code_role' => 'PHARMACIEN_CHEF_ANTENNE',
            'province_id' => 2,
            'ville' => 'Franceville',
            'last_seen_at' => now(),
        ]);

        Sanctum::actingAs($pog);

        $res = $this->getJson('/api/v1/presence/online');
        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();

        $this->assertContains($pogColleague->id, $ids);
        $this->assertNotContains($pog->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_cannot_send_note_across_provinces(): void
    {
        $pog = User::factory()->create([
            'email' => 'a.pog@opn.ga',
            'password' => Hash::make('secret'),
            'province_id' => 8,
            'ville' => 'Port-Gentil',
        ]);
        $frv = User::factory()->create([
            'email' => 'b.frv@opn.ga',
            'password' => Hash::make('secret'),
            'province_id' => 2,
            'ville' => 'Franceville',
        ]);

        Sanctum::actingAs($pog);

        $this->postJson('/api/v1/presence/note', [
            'recipient_id' => $frv->id,
            'body' => 'Test hors province',
        ])->assertForbidden();
    }
}
