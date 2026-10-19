<?php

namespace Database\Seeders;

use App\Models\AntenneProvinciale;
use App\Models\Profil;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Un chef d'antenne + un demandeur pharmacie pour chacune des 9 provinces.
 */
class ProvincesActeursSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::catalogue() as $row) {
            self::ecrire($row);
        }
    }

    /**
     * Crée ou répare un compte démo provincial lorsque l'email et le mot de passe
     * officiels sont présentés. La base Railway n'est pas re-seedée au déploiement.
     */
    public static function assurer(string $email, string $password): bool
    {
        $email = mb_strtolower(trim($email));

        foreach (self::catalogue() as $row) {
            $slot = null;
            if (mb_strtolower($row['chef']['email']) === $email) {
                $slot = 'chef';
            } elseif (mb_strtolower($row['demandeur']['email']) === $email) {
                $slot = 'demandeur';
            }

            if ($slot === null) {
                continue;
            }

            if (! hash_equals($row[$slot]['password'], $password)) {
                return false;
            }

            return self::ecrire($row);
        }

        return false;
    }

    private static function catalogue(): array
    {
        return [
            [
                'code' => 'EST',
                'slug' => 'est',
                'province' => 'Estuaire',
                'chef_lieu' => 'Libreville / Owendo',
                'chef' => [
                    'name' => 'Mme Sylvie MINTSA NZIGOU',
                    'email' => 'chef.antenne.est@opn.ga',
                    'password' => 'OPN2026@Estuaire',
                    'matricule' => 'OPN-ANT-G1',
                    'telephone' => '+241 011 70 21 01',
                ],
                'demandeur' => [
                    'name' => 'Dr. Patrick ESSONO MBA',
                    'email' => 'pharmacie.est@opn.ga',
                    'password' => 'OPN2026@PharmaEst',
                    'matricule' => 'OPN-DEM-G1',
                    'telephone' => '+241 066 10 21 01',
                    'entite' => 'Pharmacie Centrale Owendo - Estuaire',
                ],
            ],
            [
                'code' => 'G2-FRV',
                'slug' => 'frv',
                'province' => 'Haut-Ogooué',
                'chef_lieu' => 'Franceville',
                'chef' => [
                    'name' => 'M. Brice ELLA MBA',
                    'email' => 'chef.antenne.frv@opn.ga',
                    'password' => 'OPN2026@Franceville',
                    'matricule' => 'OPN-ANT-G2',
                    'telephone' => '+241 066 45 88 12',
                ],
                'demandeur' => [
                    'name' => 'Dr. Nadège MOUSSAVOU',
                    'email' => 'pharmacie.demandeur.frv@opn.ga',
                    'password' => 'OPN2026@PharmaFrv',
                    'matricule' => 'OPN-DEM-G2',
                    'telephone' => '+241 066 45 88 20',
                    'entite' => 'Pharmacie Centrale Franceville',
                ],
            ],
            [
                'code' => 'G3-LAM',
                'slug' => 'lam',
                'province' => 'Moyen-Ogooué',
                'chef_lieu' => 'Lambaréné',
                'chef' => [
                    'name' => 'Dr. Paulin ONDO METOU',
                    'email' => 'chef.antenne.lam@opn.ga',
                    'password' => 'OPN2026@Lambarene',
                    'matricule' => 'OPN-ANT-G3',
                    'telephone' => '+241 062 18 90 44',
                ],
                'demandeur' => [
                    'name' => 'Dr. Aline NZENGUE',
                    'email' => 'pharmacie.lam@opn.ga',
                    'password' => 'OPN2026@PharmaLam',
                    'matricule' => 'OPN-DEM-G3',
                    'telephone' => '+241 062 18 90 50',
                    'entite' => 'Pharmacie Albert Schweitzer - Lambaréné',
                ],
            ],
            [
                'code' => 'G4-MOU',
                'slug' => 'mou',
                'province' => 'Ngounié',
                'chef_lieu' => 'Mouila',
                'chef' => [
                    'name' => 'Mme Patricia BOUSSOUGOU',
                    'email' => 'chef.antenne.mou@opn.ga',
                    'password' => 'OPN2026@Mouila',
                    'matricule' => 'OPN-ANT-G4',
                    'telephone' => '+241 077 92 11 10',
                ],
                'demandeur' => [
                    'name' => 'Dr. Serge MBOUMBA',
                    'email' => 'pharmacie.mou@opn.ga',
                    'password' => 'OPN2026@PharmaMou',
                    'matricule' => 'OPN-DEM-G4',
                    'telephone' => '+241 077 92 11 20',
                    'entite' => 'Pharmacie Centrale Mouila',
                ],
            ],
            [
                'code' => 'G5-TCH',
                'slug' => 'tch',
                'province' => 'Nyanga',
                'chef_lieu' => 'Tchibanga',
                'chef' => [
                    'name' => 'M. Dieudonné MOUSSAVOU',
                    'email' => 'chef.antenne.tch@opn.ga',
                    'password' => 'OPN2026@Tchibanga',
                    'matricule' => 'OPN-ANT-G5',
                    'telephone' => '+241 066 33 21 89',
                ],
                'demandeur' => [
                    'name' => 'Dr. Clarisse MOUSSAVOU',
                    'email' => 'pharmacie.tch@opn.ga',
                    'password' => 'OPN2026@PharmaTch',
                    'matricule' => 'OPN-DEM-G5',
                    'telephone' => '+241 066 33 21 95',
                    'entite' => 'Pharmacie Centrale Tchibanga',
                ],
            ],
            [
                'code' => 'G6-MAK',
                'slug' => 'mak',
                'province' => 'Ogooué-Ivindo',
                'chef_lieu' => 'Makokou',
                'chef' => [
                    'name' => 'Dr. Serge MEZUI M\'ELLA',
                    'email' => 'chef.antenne.mak@opn.ga',
                    'password' => 'OPN2026@Makokou',
                    'matricule' => 'OPN-ANT-G6',
                    'telephone' => '+241 074 81 09 33',
                ],
                'demandeur' => [
                    'name' => 'Dr. Hervé NDONG',
                    'email' => 'pharmacie.mak@opn.ga',
                    'password' => 'OPN2026@PharmaMak',
                    'matricule' => 'OPN-DEM-G6',
                    'telephone' => '+241 074 81 09 40',
                    'entite' => 'Pharmacie CHR Makokou',
                ],
            ],
            [
                'code' => 'G7-KLM',
                'slug' => 'klm',
                'province' => 'Ogooué-Lolo',
                'chef_lieu' => 'Koula-Moutou',
                'chef' => [
                    'name' => 'Mme Clarisse MBOUMBA BIVEGHE',
                    'email' => 'chef.antenne.klm@opn.ga',
                    'password' => 'OPN2026@KoulaMoutou',
                    'matricule' => 'OPN-ANT-G7',
                    'telephone' => '+241 065 47 18 20',
                ],
                'demandeur' => [
                    'name' => 'Dr. Yannick OBAME',
                    'email' => 'pharmacie.klm@opn.ga',
                    'password' => 'OPN2026@PharmaKlm',
                    'matricule' => 'OPN-DEM-G7',
                    'telephone' => '+241 065 47 18 30',
                    'entite' => 'Pharmacie Centrale Koula-Moutou',
                ],
            ],
            [
                'code' => 'G8-POG',
                'slug' => 'pog',
                'province' => 'Ogooué-Maritime',
                'chef_lieu' => 'Port-Gentil',
                'chef' => [
                    'name' => 'M. Marc ANGUILET',
                    'email' => 'chef.antenne.pog@opn.ga',
                    'password' => 'OPN2026@PortGentil',
                    'matricule' => 'OPN-ANT-G8',
                    'telephone' => '+241 077 14 55 90',
                ],
                'demandeur' => [
                    'name' => 'Dr. Marie-Claire OGANDAGA',
                    'email' => 'pharmacien.pog@opn.ga',
                    'password' => 'OPN2026@PharmaciePog',
                    'matricule' => 'OPN-DEM-G8',
                    'telephone' => '+241 065 99 88 77',
                    'entite' => 'Pharmacie Centrale du Littoral - Port-Gentil',
                ],
            ],
            [
                'code' => 'G9-OYE',
                'slug' => 'oye',
                'province' => 'Woleu-Ntem',
                'chef_lieu' => 'Oyem',
                'chef' => [
                    'name' => 'Dr. Emmanuel NDONG ASSOUMOU',
                    'email' => 'chef.antenne.oye@opn.ga',
                    'password' => 'OPN2026@Oyem',
                    'matricule' => 'OPN-ANT-G9',
                    'telephone' => '+241 066 89 74 10',
                ],
                'demandeur' => [
                    'name' => 'Dr. Lucie ASSOUMOU',
                    'email' => 'pharmacie.oye@opn.ga',
                    'password' => 'OPN2026@PharmaOye',
                    'matricule' => 'OPN-DEM-G9',
                    'telephone' => '+241 066 89 74 20',
                    'entite' => 'Pharmacie Centrale Oyem',
                ],
            ],
        ];
    }

    private static function ecrire(array $row): bool
    {
        $antenne = AntenneProvinciale::where('code_antenne', $row['code'])->first();
        if (! $antenne) {
            return false;
        }

        $profilChef = Profil::where('code', 'chef_antenne')->value('id');
        $profilDemandeur = Profil::where('code', 'demandeur')->value('id');

        User::updateOrCreate(
            ['email' => $row['chef']['email']],
            [
                'name' => $row['chef']['name'],
                'password' => $row['chef']['password'],
                'role' => "Chef d'Antenne Provinciale {$row['province']}",
                'code_role' => 'CHEF_ANTENNE_PROVINCIALE',
                'entite' => "Antenne Provinciale {$row['province']} - {$row['chef_lieu']}",
                'province_id' => $antenne->id,
                'antenne_id' => $antenne->id,
                'profil_id' => $profilChef,
                'matricule_opn' => $row['chef']['matricule'],
                'telephone' => $row['chef']['telephone'],
                    'etat' => 'actif',
                    'is_2fa_enabled' => false,
                ]
            );

            User::updateOrCreate(
                ['email' => $row['demandeur']['email']],
                [
                    'name' => $row['demandeur']['name'],
                    'password' => $row['demandeur']['password'],
                    'role' => "Demandeur - Pharmacien d'Officine {$row['chef_lieu']}",
                    'code_role' => 'DEMANDEUR_PHARMACIEN',
                    'entite' => $row['demandeur']['entite'],
                    'province_id' => $antenne->id,
                    'antenne_id' => $antenne->id,
                    'profil_id' => $profilDemandeur,
                    'matricule_opn' => $row['demandeur']['matricule'],
                    'telephone' => $row['demandeur']['telephone'],
                    'etat' => 'actif',
                    'is_2fa_enabled' => false,
                ]
            );

        return true;
    }
}
