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

        /* Patrick ONDO (sa) et Admin Métier ne sont pas modifiés ici. */
        $profils = [
            ['sa', 'Super administrateur / Admin IT', 9, 'SUPER_ADMIN', 'superadmin@opn.ga'],
            ['admin_metier', 'Admin métier', 8, 'ADMIN_METIER', 'admin.metier@opn.ga'],
        ];

        /*
         * Anciens profils de connexion. Par défaut, seuls le flux et le patrimoine
         * sont ouverts. Les administrateurs ajoutent les autres droits ensuite.
         */
        $profilsHistoriques = [
            ['admin_it', 'Administrateur IT & Cyber-Sécurité', 8, 'ADMIN_IT', 'admin.it@opn.ga'],
            ['dg', 'Directeur Général', 5, 'DG', 'jb.obame@opn.ga'],
            ['dir', 'Directeur des Opérations & Pilotage', 4, 'DIR', 'dir.operations@opn.ga'],
            ['daf', 'Directrice Administrative & Financière', 4, 'DAF', 'daf@opn.ga'],
            ['appro_national', 'Directrice des Approvisionnements Nationaux', 3, 'APPRO_NATIONAL', 's.ntsame@opn.ga'],
            ['collaborateur_appro', 'Collaborateur Approvisionnements', 2, 'COLLABORATEUR_APPRO', 'g.ndzie@opn.ga'],
            ['achats', 'Responsable Achats & Marchés Fournisseurs', 2, 'ACHATS', 'achats@opn.ga'],
            ['pharmacien_national', 'Pharmacien Chef National', 2, 'PHARMACIEN_CHEF_NATIONAL', 'pharmacien.national@opn.ga'],
            ['pharmacien_antenne', 'Pharmacien Chef d\'Antenne Haut-Ogooué', 2, 'PHARMACIEN_CHEF_ANTENNE', 'pharmacie.frv@opn.ga'],
            ['chef_antenne', 'Chef d\'Antenne Provinciale Ogooué-Maritime', 2, 'CHEF_ANTENNE_PROVINCIALE', 'chef.antenne.pog@opn.ga'],
            ['urgences', 'Coordonnatrice Nationale des Urgences Sanitaires', 2, 'COORDINATEUR_URGENCES', 'urgences@opn.ga'],
            ['logistique', 'Gestionnaire Logistique & Flotte Convois', 2, 'GESTIONNAIRE_LOGISTIQUE', 'logistique@opn.ga'],
            ['surete', 'Chef de Convoi Sécurisé & Sûreté', 1, 'CHEF_CONVOI_SECURITE', 'securite.convois@opn.ga'],
            ['convoyeur', 'Convoyeur Opérationnel de Fret Pharmaceutique', 1, 'CONVOYEUR', 'convoyeur@opn.ga'],
            ['demandeur', 'Demandeur - Pharmacien d\'Officine Port-Gentil', 0, 'DEMANDEUR_PHARMACIEN', 'pharmacien.pog@opn.ga'],
            ['demandeur_structure', 'Demandeur - Direction CHR Port-Gentil', 0, 'DEMANDEUR_STRUCTURE', 'structure.pog@opn.ga'],
            ['communication', 'Responsable Communication & Relations Extérieures', 1, 'COMMUNICATION', 'communication@opn.ga'],
            ['auditeur', 'Auditeur Interne & Conformité Réglementaire', 1, 'AUDITEUR_INTERNE', 'audit.interne@opn.ga'],
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

        foreach ($profilsHistoriques as [$code, $libelle, $niveau, $role, $email]) {
            $profil = Profil::updateOrCreate(
                ['code' => $code],
                ['libelle' => $libelle, 'niveau_validation' => $niveau, 'systeme' => true]
            );
            $ids[$code] = $profil->id;
            User::where('email', $email)->update([
                'profil_id' => $profil->id,
                'etat' => 'actif',
                'code_role' => $role,
            ]);
        }

        /* Le second collaborateur Appro partage le même profil. */
        if (isset($ids['collaborateur_appro'])) {
            User::where('email', 'j.obiang@opn.ga')->update([
                'profil_id' => $ids['collaborateur_appro'],
                'etat' => 'actif',
                'code_role' => 'COLLABORATEUR_APPRO',
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

        /*
         * Chaque profil reçoit les écrans du super administrateur qui correspondent
         * à son métier, plus la chaîne, le référentiel et le centre de services.
         * Le patrimoine reste réservé à la direction.
         */
        $commun = ['ACCESS_FLUX', 'ACCESS_REFERENTIEL', 'ACCESS_SERVICES'];
        $direction = array_merge($commun, ['ACCESS_PATRIMOINE']);
        $menus = [
            'admin_it' => array_merge($commun, ['ACCESS_ADMIN', 'ACCESS_CONNECT']),
            'dg' => array_merge($direction, [
                'ACCESS_TABLEAU_BORD', 'ACCESS_MARGE', 'ACCESS_PROSPECTION', 'ACCESS_RECOUVREMENT',
                'ACCESS_INVENTAIRES', 'ACCESS_PLANIFICATION', 'ACCESS_DISTRIBUTION', 'ACCESS_ENTREPOTS',
                'ACCESS_FACTURATION', 'ACCESS_DEMANDES', 'ACCESS_APPROVISIONNEMENTS', 'ACCESS_ACHATS',
                'ACCESS_QUALITE', 'ACCESS_LOGISTIQUE', 'ACCESS_ANTENNES', 'ACCESS_PORTAIL',
                'ACCESS_AGENTS', 'ACCESS_GMAO', 'ACCESS_URGENCES', 'ACCESS_CONNECT', 'ACCESS_ADMIN',
            ]),
            'dir' => array_merge($direction, [
                'ACCESS_TABLEAU_BORD', 'ACCESS_MARGE', 'ACCESS_PROSPECTION', 'ACCESS_INVENTAIRES',
                'ACCESS_PLANIFICATION', 'ACCESS_DISTRIBUTION', 'ACCESS_ENTREPOTS', 'ACCESS_FACTURATION',
                'ACCESS_DEMANDES', 'ACCESS_APPROVISIONNEMENTS', 'ACCESS_ACHATS', 'ACCESS_QUALITE',
                'ACCESS_LOGISTIQUE', 'ACCESS_ANTENNES', 'ACCESS_PORTAIL', 'ACCESS_AGENTS',
                'ACCESS_GMAO', 'ACCESS_URGENCES', 'ACCESS_CONNECT',
            ]),
            'daf' => array_merge($direction, [
                'ACCESS_FACTURATION', 'ACCESS_RECOUVREMENT', 'ACCESS_MARGE', 'ACCESS_INVENTAIRES',
                'ACCESS_PLANIFICATION', 'ACCESS_AGENTS', 'ACCESS_ENTREPOTS', 'ACCESS_DISTRIBUTION',
                'ACCESS_DEMANDES', 'ACCESS_TABLEAU_BORD', 'ACCESS_ACHATS', 'ACCESS_APPROVISIONNEMENTS',
                'ACCESS_LOGISTIQUE', 'ACCESS_CONNECT',
            ]),
            'appro_national' => array_merge($direction, [
                'ACCESS_PLANIFICATION', 'ACCESS_DISTRIBUTION', 'ACCESS_ENTREPOTS', 'ACCESS_DEMANDES',
                'ACCESS_APPROVISIONNEMENTS', 'ACCESS_ACHATS', 'ACCESS_QUALITE', 'ACCESS_LOGISTIQUE',
                'ACCESS_ANTENNES', 'ACCESS_PORTAIL', 'ACCESS_TABLEAU_BORD', 'ACCESS_STOCKS',
                'ACCESS_URGENCES', 'ACCESS_CONNECT',
            ]),
            'collaborateur_appro' => array_merge($commun, [
                'ACCESS_PLANIFICATION', 'ACCESS_DISTRIBUTION', 'ACCESS_ENTREPOTS', 'ACCESS_DEMANDES',
                'ACCESS_APPROVISIONNEMENTS', 'ACCESS_LOGISTIQUE', 'ACCESS_TABLEAU_BORD',
                'ACCESS_STOCKS', 'ACCESS_URGENCES', 'ACCESS_CONNECT',
            ]),
            'achats' => array_merge($commun, [
                'ACCESS_ACHATS', 'ACCESS_DEMANDES', 'ACCESS_STOCKS', 'ACCESS_APPROVISIONNEMENTS', 'ACCESS_CONNECT',
            ]),
            'pharmacien_national' => array_merge($commun, [
                'ACCESS_PLANIFICATION', 'ACCESS_PORTAIL', 'ACCESS_GMAO', 'ACCESS_ENTREPOTS',
                'ACCESS_QUALITE', 'ACCESS_STOCKS', 'ACCESS_DISTRIBUTION', 'ACCESS_TABLEAU_BORD',
                'ACCESS_DEMANDES', 'ACCESS_URGENCES', 'ACCESS_CONNECT',
            ]),
            'chef_antenne' => array_merge($commun, [
                'ACCESS_ANTENNES', 'ACCESS_PROSPECTION', 'ACCESS_INVENTAIRES', 'ACCESS_MARGE',
                'ACCESS_FACTURATION', 'ACCESS_RECOUVREMENT', 'ACCESS_DISTRIBUTION', 'ACCESS_DEMANDES',
                'ACCESS_STOCKS', 'ACCESS_LOGISTIQUE', 'ACCESS_APPROVISIONNEMENTS', 'ACCESS_URGENCES', 'ACCESS_CONNECT',
            ]),
            'pharmacien_antenne' => array_merge($commun, [
                'ACCESS_ANTENNES', 'ACCESS_INVENTAIRES', 'ACCESS_STOCKS', 'ACCESS_QUALITE',
                'ACCESS_DISTRIBUTION', 'ACCESS_DEMANDES', 'ACCESS_LOGISTIQUE', 'ACCESS_APPROVISIONNEMENTS',
                'ACCESS_URGENCES', 'ACCESS_CONNECT',
            ]),
            'urgences' => array_merge($commun, [
                'ACCESS_URGENCES', 'ACCESS_PLANIFICATION', 'ACCESS_DISTRIBUTION', 'ACCESS_QUALITE',
                'ACCESS_DEMANDES', 'ACCESS_LOGISTIQUE', 'ACCESS_STOCKS', 'ACCESS_CONNECT',
            ]),
            'logistique' => array_merge($commun, [
                'ACCESS_DISTRIBUTION', 'ACCESS_GMAO', 'ACCESS_ENTREPOTS', 'ACCESS_LOGISTIQUE',
                'ACCESS_ANTENNES', 'ACCESS_DEMANDES', 'ACCESS_APPROVISIONNEMENTS', 'ACCESS_URGENCES', 'ACCESS_CONNECT',
            ]),
            'surete' => array_merge($commun, [
                'ACCESS_DISTRIBUTION', 'ACCESS_LOGISTIQUE', 'ACCESS_DEMANDES', 'ACCESS_APPROVISIONNEMENTS',
                'ACCESS_URGENCES', 'ACCESS_CONNECT',
            ]),
            'convoyeur' => array_merge($commun, [
                'ACCESS_DISTRIBUTION', 'ACCESS_LOGISTIQUE', 'ACCESS_DEMANDES', 'ACCESS_URGENCES', 'ACCESS_CONNECT',
            ]),
            'demandeur' => array_merge($commun, ['ACCESS_DEMANDES', 'ACCESS_FACTURATION', 'ACCESS_STOCKS', 'ACCESS_CONNECT']),
            'demandeur_structure' => array_merge($commun, ['ACCESS_DEMANDES', 'ACCESS_FACTURATION', 'ACCESS_STOCKS', 'ACCESS_CONNECT']),
            'communication' => array_merge($commun, ['ACCESS_DEMANDES', 'ACCESS_CONNECT']),
            'auditeur' => array_merge($commun, [
                'ACCESS_ADMIN', 'ACCESS_TABLEAU_BORD', 'ACCESS_GMAO', 'ACCESS_PORTAIL', 'ACCESS_PLANIFICATION',
                'ACCESS_DISTRIBUTION', 'ACCESS_ENTREPOTS', 'ACCESS_FACTURATION', 'ACCESS_DEMANDES',
                'ACCESS_ACHATS', 'ACCESS_QUALITE', 'ACCESS_LOGISTIQUE', 'ACCESS_ANTENNES',
                'ACCESS_APPROVISIONNEMENTS', 'ACCESS_CONNECT',
            ]),
        ];
        foreach ($menus as $profilCode => $codes) {
            if (!isset($ids[$profilCode])) {
                continue;
            }
            HabilitationProfil::where('profil_id', $ids[$profilCode])->delete();
            foreach (array_unique($codes) as $code) {
                if (!isset($all[$code])) {
                    continue;
                }
                $this->grant($ids[$profilCode], $all[$code], $this->access());
            }
        }

        /* Le demandeur consulte le flux et crée ses demandes. Il ne transmet ni ne disperse. */
        $flux = $all['flux'] ?? null;
        foreach (['demandeur', 'demandeur_structure'] as $codeDemandeur) {
            if ($flux && isset($ids[$codeDemandeur])) {
                $this->grant($ids[$codeDemandeur], $flux, ['creer' => true, 'lire' => true]);
            }
        }

        $this->rattacherSansProfil($ids);
    }

    private function rattacherSansProfil(array $ids): void
    {
        $parRole = [
            'SUPER_ADMIN' => 'sa',
            'ADMIN_METIER' => 'admin_metier',
            'ADMIN_IT' => 'admin_it',
            'DG' => 'dg',
            'DIR' => 'dir',
            'DAF' => 'daf',
            'APPRO_NATIONAL' => 'appro_national',
            'COLLABORATEUR_APPRO' => 'collaborateur_appro',
            'ACHATS' => 'achats',
            'PHARMACIEN_CHEF_NATIONAL' => 'pharmacien_national',
            'PHARMACIEN_CHEF_ANTENNE' => 'pharmacien_antenne',
            'CHEF_ANTENNE_PROVINCIALE' => 'chef_antenne',
            'COORDINATEUR_URGENCES' => 'urgences',
            'GESTIONNAIRE_LOGISTIQUE' => 'logistique',
            'CHEF_CONVOI_SECURITE' => 'surete',
            'CONVOYEUR' => 'convoyeur',
            'DEMANDEUR_PHARMACIEN' => 'demandeur',
            'DEMANDEUR_STRUCTURE' => 'demandeur_structure',
            'COMMUNICATION' => 'communication',
            'AUDITEUR_INTERNE' => 'auditeur',
        ];
        foreach ($parRole as $role => $profilCode) {
            if (!isset($ids[$profilCode])) {
                continue;
            }
            User::query()
                ->where('code_role', $role)
                ->whereNull('profil_id')
                ->update([
                    'profil_id' => $ids[$profilCode],
                    'etat' => 'actif',
                ]);
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
