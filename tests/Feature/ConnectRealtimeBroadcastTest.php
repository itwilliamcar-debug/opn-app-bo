<?php

namespace Tests\Feature;

use App\Events\ConnectMessageCreated;
use App\Models\ConnectConversation;
use App\Models\ConnectMessage;
use App\Models\User;
use Database\Seeders\ConnectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConnectRealtimeBroadcastTest extends TestCase
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

        $this->seed(ConnectSeeder::class);
    }

    public function test_member_can_authorize_private_connect_channel(): void
    {
        $dg = User::where('email', 'jb.obame@opn.ga')->firstOrFail();
        $conversation = ConnectConversation::query()
            ->whereHas('members', fn ($q) => $q->where('user_id', $dg->id))
            ->firstOrFail();

        Sanctum::actingAs($dg);

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-connect.conversation.'.$conversation->id,
        ])->assertOk();
    }

    public function test_non_member_cannot_authorize_private_connect_channel(): void
    {
        $outsider = User::factory()->create([
            'name' => 'M. Outsider TEST',
            'email' => 'outsider@opn.ga',
            'password' => Hash::make('OPN2026@Test'),
            'code_role' => 'AUDITEUR_INTERNE',
            'role' => 'Auditeur',
            'entite' => 'Audit',
        ]);
        $conversation = ConnectConversation::query()
            ->whereHas('members', fn ($q) => $q->where('user_id', User::where('email', 'jb.obame@opn.ga')->value('id')))
            ->firstOrFail();

        Sanctum::actingAs($outsider);

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-connect.conversation.'.$conversation->id,
        ])->assertForbidden();
    }

    public function test_sending_message_dispatches_connect_message_created(): void
    {
        Event::fake([ConnectMessageCreated::class]);

        $dg = User::where('email', 'jb.obame@opn.ga')->firstOrFail();
        $conversation = ConnectConversation::query()->firstOrFail();

        Sanctum::actingAs($dg);

        $this->postJson('/api/v1/connect/conversations/'.$conversation->id.'/messages', [
            'type' => 'text',
            'body' => 'Test temps réel Libreville',
        ])->assertCreated();

        Event::assertDispatched(ConnectMessageCreated::class, function (ConnectMessageCreated $event) use ($conversation) {
            return (int) $event->message->conversation_id === (int) $conversation->id
                && $event->message->body === 'Test temps réel Libreville';
        });
    }

    public function test_connect_message_created_implements_should_broadcast_now(): void
    {
        $dg = User::where('email', 'jb.obame@opn.ga')->firstOrFail();
        $conversation = ConnectConversation::query()->firstOrFail();

        $message = ConnectMessage::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $dg->id,
            'type' => 'text',
            'body' => 'Broadcast now check',
        ]);

        $event = new ConnectMessageCreated($message);

        $this->assertInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class, $event);
        $this->assertSame('connect.message.created', $event->broadcastAs());
        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertSame('private-connect.conversation.'.$conversation->id, $channels[0]->name);
    }
}
