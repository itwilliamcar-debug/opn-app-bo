<?php

namespace Database\Seeders;

use App\Models\AntenneProvinciale;
use App\Models\Habilitation;
use App\Models\HabilitationProfil;
use App\Models\Profil;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProfilHabilitationSeeder extends Seeder
{
    public function run(): void
    {
        $habilitations = [
            ['ACCESS_FLUX', 'Accès chaîne d\'approvisionnement', 'acces'],
            ['ACCESS_REFERENTIEL', 'Accès référentiel', 'acces'],
            ['ACCESS_PATRIMOINE', 'Accès patrimoine', 'acces'],
            ['ACCESS_SERVICES', 'Accès centre de services', 'acces'],
            ['ACCESS_PARAMETRAGE', 'Accès paramétrage', 'acces'],
            ['ACCESS_ADMIN', 'Accès administration', 'acces'],
            ['ACCESS_TABLEAU_BORD', 'Accès tableau de bord', 'acces'],
            ['ACCESS_DEMANDES', 'Accès demandes', 'acces'],
            ['ACCESS_STOCKS', 'Accès stocks', 'acces'],
            ['ACCESS_APPROVISIONNEMENTS', 'Accès approvisionnements', 'acces'],
            ['ACCESS_ACHATS', 'Accès achats et marchés', 'acces'],
            ['ACCESS_QUALITE', 'Accès qualité', 'acces'],
            ['ACCESS_LOGISTIQUE', 'Accès logistique', 'acces'],
            ['ACCESS_ANTENNES', 'Accès antennes', 'acces'],
            ['ACCESS_URGENCES', 'Accès urgences', 'acces'],
            ['ACCESS_AGENTS', 'Accès agents', 'acces'],
            ['ACCESS_FACTURATION', 'Accès facturation', 'acces'],
            ['ACCESS_ENTREPOTS', 'Accès entrepôts', 'acces'],
            ['ACCESS_DISTRIBUTION', 'Accès distribution', 'acces'],
            ['ACCESS_PLANIFICATION', 'Accès planification', 'acces'],
            ['ACCESS_PORTAIL', 'Accès portail citoyen', 'acces'],
            ['ACCESS_GMAO', 'Accès maintenance', 'acces'],
            ['ACCESS_CONNECT', 'Accès messagerie', 'acces'],
            ['ACCESS_PROSPECTION', 'Accès prospection', 'acces'],
            ['ACCESS_MARGE', 'Accès marge brute', 'acces'],
            ['ACCESS_RECOUVREMENT', 'Accès recouvrement', 'acces'],
            ['ACCESS_INVENTAIRES', 'Accès inventaires', 'acces'],
            ['flux', 'Gestion des commandes', 'flux'],
            ['referentiel', 'Gestion du référentiel', 'referentiel'],
            ['patrimoine', 'Gestion du patrimoine', 'patrimoine'],
            ['utilisateurs', 'Gestion des utilisateurs', 'utilisateurs'],
            ['parametrage', 'Paramétrage système', 'parametrage'],
            ['services', 'Gestion du centre de services', 'services'],
            ['admin', 'Gestion de l\'administration', 'admin'],
            ['tableau_bord', 'Gestion du tableau de bord', 'tableau_bord'],
            ['demandes', 'Gestion des demandes', 'demandes'],
            ['stocks', 'Gestion des stocks', 'stocks'],
            ['approvisionnements', 'Gestion des approvisionnements', 'approvisionnements'],
            ['achats', 'Gestion des achats', 'achats'],
            ['qualite', 'Gestion de la qualité', 'qualite'],
            ['logistique', 'Gestion de la logistique', 'logistique'],
            ['antennes', 'Gestion des antennes', 'antennes'],
            ['urgences', 'Gestion des urgences', 'urgences'],
            ['agents', 'Gestion des agents', 'agents'],
            ['facturation', 'Gestion de la facturation', 'facturation'],
            ['entrepots', 'Gestion des entrepôts', 'entrepots'],
            ['distribution', 'Gestion de la distribution', 'distribution'],
            ['planification', 'Gestion de la planification', 'planification'],
            ['portail', 'Gestion du portail', 'portail'],
            ['gmao', 'Gestion de la maintenance', 'gmao'],
            ['connect', 'Gestion de la messagerie', 'connect'],
            ['prospection', 'Gestion de la prospection', 'prospection'],
            ['marge', 'Gestion de la marge', 'marge'],
            ['recouvrement', 'Gestion du recouvrement', 'recouvrement'],
            ['inventaires', 'Gestion des inventaires', 'inventaires'],
        ];

        foreach ($habilitations as [$code, $libelle, $module]) {
            Habilitation::updateOrCreate(['code' => $code], ['libelle' => $libelle, 'module' => $module]);
        }

        $profils = [
            ['sa', 'Super administrateur / Admin IT', 9, 'SUPER_ADMIN', 'superadmin@opn.ga'],
            ['admin_metier', 'Admin métier', 8, 'ADMIN_METIER', 'admin.metier@opn.ga'],
            ['dg', 'Directeur général', 5, 'DG', 'jb.obame@opn.ga'],
            ['daf', 'Directrice administrative et financière', 4, 'DAF', 'daf@opn.ga'],
            ['appro_national', 'Directrice des approvisionnements nationaux', 3, 'APPRO_NATIONAL', 's.ntsame@opn.ga'],
            ['demandeur', 'Demandeur', 0, 'DEMANDEUR_PHARMACIEN', 'pharmacien.pog@opn.ga'],
        ];

        $ids = [];
        foreach ($profils as [$code, $libelle, $niveau, $role, $email]) {
            $profil = Profil::updateOrCreate(
                ['code' => $code],
                ['libelle' => $libelle, 'niveau_validation' => $niveau, 'systeme' => true]
            );
            $ids[$code] = $profil->id;
            if ($code === 'admin_metier') {
                $this->assurerAdminMetier($profil->id);
                continue;
            }
            User::where('email', $email)->update([
                'profil_id' => $profil->id,
                'etat' => 'actif',
                'code_role' => $role,
            ]);
        }

        $all = Habilitation::pluck('id', 'code');
        $grants = [
            'sa' => ['*' => ['creer' => true, 'lire' => true, 'modifier' => true, 'valider' => true]],
            'admin_metier' => [
                'ACCESS_FLUX' => $this->plein(),
                'ACCESS_REFERENTIEL' => $this->plein(),
                'ACCESS_PATRIMOINE' => $this->plein(),
                'ACCESS_SERVICES' => $this->plein(),
                'ACCESS_PARAMETRAGE' => $this->plein(),
                'flux' => $this->plein(),
                'referentiel' => $this->plein(),
                'patrimoine' => $this->plein(),
                'services' => $this->plein(),
                'utilisateurs' => $this->plein(),
                'parametrage' => $this->plein(),
            ],
            'dg' => [
                'ACCESS_FLUX' => $this->access(),
                'ACCESS_PATRIMOINE' => $this->access(),
                'ACCESS_SERVICES' => $this->access(),
                'ACCESS_PARAMETRAGE' => $this->access(),
                'flux' => ['lire' => true, 'valider' => true],
                'patrimoine' => ['creer' => true, 'lire' => true, 'modifier' => true],
                'utilisateurs' => ['lire' => true],
                'parametrage' => ['lire' => true],
            ],
            'daf' => [
                'ACCESS_FLUX' => $this->access(),
                'ACCESS_PATRIMOINE' => $this->access(),
                'ACCESS_REFERENTIEL' => $this->access(),
                'ACCESS_SERVICES' => $this->access(),
                'flux' => ['lire' => true, 'valider' => true],
                'patrimoine' => ['lire' => true, 'modifier' => true],
                'referentiel' => ['lire' => true],
            ],
            'appro_national' => [
                'ACCESS_FLUX' => $this->access(),
                'ACCESS_REFERENTIEL' => $this->access(),
                'ACCESS_PATRIMOINE' => $this->access(),
                'ACCESS_SERVICES' => $this->access(),
                'flux' => ['creer' => true, 'lire' => true, 'modifier' => true, 'valider' => true],
                'referentiel' => ['lire' => true],
                'patrimoine' => ['lire' => true],
            ],
            'demandeur' => [
                'ACCESS_FLUX' => $this->access(),
                'flux' => ['creer' => true, 'lire' => true],
            ],
        ];

        foreach ($grants as $profilCode => $rows) {
            if (isset($rows['*'])) {
                foreach ($all as $code => $habilitationId) {
                    $this->grant($ids[$profilCode], $habilitationId, $rows['*']);
                }
                continue;
            }
            foreach ($rows as $code => $flags) {
                if (!isset($all[$code])) {
                    continue;
                }
                $this->grant($ids[$profilCode], $all[$code], $flags);
            }
        }
    }

    private function access(): array
    {
        return ['lire' => true];
    }

    private function plein(): array
    {
        return ['creer' => true, 'lire' => true, 'modifier' => true, 'valider' => true];
    }

    private function assurerAdminMetier(int $profilId): void
    {
        $antenneId = AntenneProvinciale::query()->orderBy('id')->value('id');
        $user = User::firstOrCreate(
            ['email' => 'admin.metier@opn.ga'],
            [
                'name' => 'Admin Métier',
                'password' => 'OPN2026@Metier',
                'role' => 'Admin métier',
                'code_role' => 'ADMIN_METIER',
                'entite' => 'Paramétrage métier OPN - Libreville',
                'province_id' => $antenneId,
                'matricule_opn' => 'OPN-MET-001',
                'telephone' => '+241 011 70 00 02',
                'etat' => 'actif',
                'is_2fa_enabled' => false,
            ]
        );
        $user->update([
            'profil_id' => $profilId,
            'etat' => 'actif',
            'code_role' => 'ADMIN_METIER',
            'role' => 'Admin métier',
            'name' => 'Admin Métier',
        ]);
    }

    private function grant(int $profilId, int $habilitationId, array $flags): void
    {
        $payload = [
            'creer' => (bool) ($flags['creer'] ?? false),
            'lire' => (bool) ($flags['lire'] ?? false),
            'modifier' => (bool) ($flags['modifier'] ?? false),
            'valider' => (bool) ($flags['valider'] ?? false),
        ];
        if (!in_array(true, $payload, true)) {
            return;
        }
        HabilitationProfil::updateOrCreate(
            ['profil_id' => $profilId, 'habilitation_id' => $habilitationId],
            $payload
        );
    }
}
