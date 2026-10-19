<?php

namespace Database\Seeders;

use App\Models\CommercialProspect;
use App\Models\CommercialVisite;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class CommercialProspectSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('commercial_prospects') || ! Schema::hasTable('commercial_visites')) {
            return;
        }

        $prospects = [
            [
                'code' => 'PROS_G9_001',
                'antenne_code' => 'G9',
                'antenne_nom' => 'Antenne Régionale Woleu-Ntem (Oyem)',
                'nom_structure' => 'Polyclinique Sainte-Marie d\'Oyem',
                'type_structure' => 'Clinique Privée',
                'province' => 'Woleu-Ntem',
                'province_code' => 'G9',
                'localite' => 'Oyem',
                'region_sanitaire' => 'Région Sanitaire Nord',
                'contact_nom' => 'Dr. Henriette NTOUTOUME',
                'telephone' => '+241 077 34 21 09',
                'email' => 'direction@polyclinique-stemarie-oyem.ga',
                'adresse' => 'Quartier Adjougou, Rue du Grand Marché',
                'gps_lat' => 1.5991,
                'gps_lng' => 11.5793,
                'photo_url' => 'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=400&h=300&fit=crop&auto=format&q=80',
                'statut' => 'DEVIS_ENVOYE',
                'date_creation' => '2026-07-12',
                'date_derniere_visite' => '2026-09-28',
                'jours_depuis_visite' => 9,
                'is_dormant' => false,
                'commercial_assignee' => 'M. Paul MEBIAME (Commercial Terrain G9)',
                'commentaire' => 'Très forte demande en solutés injectables, antipaludiques de dernière génération et gants chirurgicaux.',
                'devis_associes' => ['DeG9_26004'],
                'client_converti_id' => null,
                'visites' => [
                    [
                        'code_visite' => 'VIS-2026-089',
                        'date_visite' => '2026-07-14',
                        'commercial' => 'M. Paul MEBIAME',
                        'resultat' => 'INTERESSE',
                        'commentaire' => 'Présentation du catalogue OPN SIMPEX et mercuriale homologuée.',
                        'prochaine_action' => 'Transmettre une offre tarifaire sur 15 références d\'urgence.',
                    ],
                    [
                        'code_visite' => 'VIS-2026-112',
                        'date_visite' => '2026-09-28',
                        'commercial' => 'M. Paul MEBIAME',
                        'resultat' => 'DEVIS_DEMANDE',
                        'commentaire' => 'Remise du devis DeG9_26004 d\'un montant de 3 850 000 XAF.',
                        'prochaine_action' => 'Relancer pour validation du Bon de Commande client.',
                    ],
                ],
            ],
            [
                'code' => 'PROS_G8_002',
                'antenne_code' => 'G8',
                'antenne_nom' => 'Antenne Régionale Ogooué-Maritime (Port-Gentil)',
                'nom_structure' => 'Cabinet Médical du Littoral Mandji',
                'type_structure' => 'Cabinet Médical Privé',
                'province' => 'Ogooué-Maritime',
                'province_code' => 'G8',
                'localite' => 'Port-Gentil',
                'region_sanitaire' => 'Région Sanitaire Maritime',
                'contact_nom' => 'Dr. Alain OBOUNOU',
                'telephone' => '+241 066 90 12 45',
                'email' => 'littoral.mandji@cliniques-gabon.ga',
                'adresse' => 'Boulevard Léon Mba, Face Total Gabon',
                'gps_lat' => -0.7193,
                'gps_lng' => 8.7815,
                'photo_url' => 'https://images.unsplash.com/photo-1586773860418-d37222d8fce3?w=400&h=300&fit=crop&auto=format&q=80',
                'statut' => 'CONTACTE',
                'date_creation' => '2026-04-10',
                'date_derniere_visite' => '2026-05-15',
                'jours_depuis_visite' => 145,
                'is_dormant' => true,
                'commercial_assignee' => 'Mme Sandrine AVOMO (Commerciale POG)',
                'commentaire' => 'Client potentiel à fort pouvoir d\'achat. Nécessite une relance urgente du Responsable d\'Antenne.',
                'devis_associes' => [],
                'client_converti_id' => null,
                'visites' => [
                    [
                        'code_visite' => 'VIS-2026-042',
                        'date_visite' => '2026-05-15',
                        'commercial' => 'Mme Sandrine AVOMO',
                        'resultat' => 'A_RELANCER',
                        'commentaire' => 'Attente du renouvellement du plateau technique du cabinet.',
                        'prochaine_action' => 'Visite présentielle avec échantillonnage DM.',
                    ],
                ],
            ],
            [
                'code' => 'PROS_G2_003',
                'antenne_code' => 'G2',
                'antenne_nom' => 'Antenne Régionale Haut-Ogooué (Franceville)',
                'nom_structure' => 'Pharmacie de la Mpassa',
                'type_structure' => 'Pharmacie d\'Officine',
                'province' => 'Haut-Ogooué',
                'province_code' => 'G2',
                'localite' => 'Franceville',
                'region_sanitaire' => 'Région Sanitaire Sud-Est',
                'contact_nom' => 'Dr. Clarisse OBIANG',
                'telephone' => '+241 074 88 77 66',
                'email' => 'mpassa.officine@gmail.com',
                'adresse' => 'Avenue Mgr Raponda Walker, Centre-Ville',
                'gps_lat' => -1.6333,
                'gps_lng' => 13.5833,
                'photo_url' => 'https://images.unsplash.com/photo-1576602976047-174e57a47881?w=400&h=300&fit=crop&auto=format&q=80',
                'statut' => 'CONVERTI',
                'date_creation' => '2026-06-01',
                'date_derniere_visite' => '2026-08-14',
                'jours_depuis_visite' => 54,
                'is_dormant' => false,
                'commercial_assignee' => 'M. Guy NGUEMA (Commercial G2)',
                'commentaire' => 'Converti en Client officiel suite à la commande et facture FaG2_26019.',
                'devis_associes' => ['DeG2_26012'],
                'client_converti_id' => 'N0084',
                'visites' => [
                    [
                        'code_visite' => 'VIS-2026-071',
                        'date_visite' => '2026-06-05',
                        'commercial' => 'M. Guy NGUEMA',
                        'resultat' => 'INTERESSE',
                        'commentaire' => 'Présentation des remises officines OPN.',
                        'prochaine_action' => 'Établissement du devis initial DeG2_26012.',
                    ],
                    [
                        'code_visite' => 'VIS-2026-095',
                        'date_visite' => '2026-08-14',
                        'commercial' => 'M. Guy NGUEMA',
                        'resultat' => 'COMMANDE_PASSEE',
                        'commentaire' => 'Signature du bon de commande. Conversion client effectuée.',
                        'prochaine_action' => 'Suivi du premier réapprovisionnement.',
                    ],
                ],
            ],
            [
                'code' => 'PROS_G6_004',
                'antenne_code' => 'G6',
                'antenne_nom' => 'Antenne Régionale Ogooué-Ivindo (Makokou)',
                'nom_structure' => 'Centre Médico-Chirurgical de l\'Ivindo',
                'type_structure' => 'Centre Médical Privé',
                'province' => 'Ogooué-Ivindo',
                'province_code' => 'G6',
                'localite' => 'Makokou',
                'region_sanitaire' => 'Région Sanitaire Nord-Est',
                'contact_nom' => 'Dr. Serge MEZUI',
                'telephone' => '+241 077 65 43 21',
                'email' => 'cmc.ivindo@sante-ga.org',
                'adresse' => 'Quartier Epassendje, Rte de Booué',
                'gps_lat' => 0.5638,
                'gps_lng' => 12.8642,
                'photo_url' => 'https://images.unsplash.com/photo-1538108149393-fbbd81895907?w=400&h=300&fit=crop&auto=format&q=80',
                'statut' => 'IDENTIFIE',
                'date_creation' => '2026-09-02',
                'date_derniere_visite' => '2026-09-10',
                'jours_depuis_visite' => 27,
                'is_dormant' => false,
                'commercial_assignee' => 'Mme Nadège ONDO (Commerciale G6)',
                'commentaire' => 'Structure récemment ouverte. Forte patientèle minière.',
                'devis_associes' => [],
                'client_converti_id' => null,
                'visites' => [
                    [
                        'code_visite' => 'VIS-2026-104',
                        'date_visite' => '2026-09-10',
                        'commercial' => 'Mme Nadège ONDO',
                        'resultat' => 'INTERESSE',
                        'commentaire' => 'Prise de contact avec le médecin-chef.',
                        'prochaine_action' => 'Planifier démonstration produits mère-enfant.',
                    ],
                ],
            ],
        ];

        foreach ($prospects as $row) {
            $visites = $row['visites'] ?? [];
            unset($row['visites']);

            $prospect = CommercialProspect::updateOrCreate(
                ['code' => $row['code']],
                $row
            );

            foreach ($visites as $visite) {
                CommercialVisite::updateOrCreate(
                    ['code_visite' => $visite['code_visite']],
                    array_merge($visite, ['prospect_id' => $prospect->id])
                );
            }
        }
    }
}
