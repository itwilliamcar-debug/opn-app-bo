<?php

namespace Database\Seeders;

use App\Models\ConnectConversation;
use App\Models\ConnectConversationMember;
use App\Models\ConnectMessage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Private OPN Connect sample conversations only — no public channel.
 */
class ConnectSeeder extends Seeder
{
    public function run(): void
    {
        $dg = User::where('email', 'jb.obame@opn.ga')->first();
        $appro = User::where('email', 's.ntsame@opn.ga')->first();
        $grace = User::where('email', 'g.ndzie@opn.ga')->first();
        $jean = User::where('email', 'j.obiang@opn.ga')->first();

        if (! $dg || ! $appro || ! $grace || ! $jean) {
            $this->command?->warn('ConnectSeeder: utilisateurs OPN introuvables — seed Connect ignoré.');

            return;
        }

        DB::transaction(function () use ($dg, $appro, $grace, $jean) {
            $direct = $this->upsertConversation(
                uuid: 'aaaaaaaa-bbbb-4ccc-8ddd-111111111111',
                type: 'direct',
                title: 'DG ↔ Approvisionnements',
                description: 'Canal privé Direction Générale / Approvisionnements Nationaux',
                jitsiRoom: 'opn-connect-dg-appro-private',
                createdBy: $dg->id,
            );

            $this->syncMembers($direct, [
                ['user_id' => $dg->id, 'role' => 'owner'],
                ['user_id' => $appro->id, 'role' => 'member'],
            ]);

            $this->seedMessagesIfEmpty($direct, [
                [
                    'user_id' => $dg->id,
                    'type' => 'text',
                    'body' => 'Bonjour Sylvie, merci de me faire un point sur les stocks ACT Owendo avant le COD de demain.',
                ],
                [
                    'user_id' => $appro->id,
                    'type' => 'text',
                    'body' => 'Bien reçu Dr OBAME. Couverture nationale à 42 jours ; je vous envoie le détail Haut-Ogooué ce soir.',
                ],
            ]);

            $group = $this->upsertConversation(
                uuid: 'bbbbbbbb-cccc-4ddd-8eee-222222222222',
                type: 'group',
                title: 'Cellule Appro Owendo',
                description: 'Coordination privée Appro + Collaborateurs (Grâce NDZIÉ, Jean OBIANG)',
                jitsiRoom: 'opn-connect-cellule-appro-owendo',
                createdBy: $appro->id,
            );

            $this->syncMembers($group, [
                ['user_id' => $appro->id, 'role' => 'owner'],
                ['user_id' => $grace->id, 'role' => 'member'],
                ['user_id' => $jean->id, 'role' => 'member'],
            ]);

            $this->seedMessagesIfEmpty($group, [
                [
                    'user_id' => $appro->id,
                    'type' => 'text',
                    'body' => 'Équipe, priorisez la préparation du convoi maritime Port-Gentil (LIV-POG-2026-012).',
                ],
                [
                    'user_id' => $grace->id,
                    'type' => 'text',
                    'body' => 'Les bons FEFO ACT et Paracétamol injectable sont prêts sur le Rack A-04.',
                ],
                [
                    'user_id' => $jean->id,
                    'type' => 'emoji',
                    'body' => '🎉',
                    'emoji_code' => 'party_popper',
                ],
            ]);

            $dir = User::where('email', 'dir.operations@opn.ga')->first();
            $daf = User::where('email', 'daf@opn.ga')->first();
            if ($dir && $daf) {
                $council = $this->upsertConversation(
                    uuid: 'cccccccc-dddd-4eee-8fff-333333333333',
                    type: 'group',
                    title: 'Comité Visas DIR · DAF · DG',
                    description: 'Appels vidéo et coordination hiérarchique des visas nationaux',
                    jitsiRoom: 'opn-comite-visas-hierarchie',
                    createdBy: $dg->id,
                );

                $this->syncMembers($council, [
                    ['user_id' => $dg->id, 'role' => 'owner'],
                    ['user_id' => $dir->id, 'role' => 'member'],
                    ['user_id' => $daf->id, 'role' => 'member'],
                    ['user_id' => $appro->id, 'role' => 'member'],
                ]);

                $this->seedMessagesIfEmpty($council, [
                    [
                        'user_id' => $dg->id,
                        'type' => 'text',
                        'body' => 'Comité visas : ouvrez la salle visio pour le point hebdomadaire DIR/DAF/Appro.',
                    ],
                ]);
            }
        });
    }

    private function upsertConversation(
        string $uuid,
        string $type,
        string $title,
        string $description,
        string $jitsiRoom,
        int $createdBy,
    ): ConnectConversation {
        return ConnectConversation::updateOrCreate(
            ['uuid' => $uuid],
            [
                'type' => $type,
                'title' => $title,
                'description' => $description,
                'jitsi_room' => $jitsiRoom,
                'created_by' => $createdBy,
                'last_message_at' => now(),
            ]
        );
    }

    /**
     * @param  list<array{user_id:int, role:string}>  $members
     */
    private function syncMembers(ConnectConversation $conversation, array $members): void
    {
        $keepIds = [];

        foreach ($members as $member) {
            ConnectConversationMember::updateOrCreate(
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => $member['user_id'],
                ],
                [
                    'role' => $member['role'],
                    'joined_at' => now()->subDays(2),
                    'last_read_at' => now()->subDay(),
                ]
            );
            $keepIds[] = $member['user_id'];
        }

        ConnectConversationMember::query()
            ->where('conversation_id', $conversation->id)
            ->whereNotIn('user_id', $keepIds)
            ->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     */
    private function seedMessagesIfEmpty(ConnectConversation $conversation, array $messages): void
    {
        if ($conversation->messages()->exists()) {
            return;
        }

        $lastAt = null;

        foreach ($messages as $index => $payload) {
            $createdAt = now()->subHours(count($messages) - $index);
            $message = ConnectMessage::create([
                'conversation_id' => $conversation->id,
                'user_id' => $payload['user_id'],
                'type' => $payload['type'],
                'body' => $payload['body'] ?? null,
                'emoji_code' => $payload['emoji_code'] ?? null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            $lastAt = $message->created_at;
        }

        if ($lastAt) {
            $conversation->update(['last_message_at' => $lastAt]);
        }
    }
}
