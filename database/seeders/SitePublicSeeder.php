<?php

namespace Database\Seeders;

use App\Models\Actualite;
use App\Models\AntenneProvinciale;
use App\Models\AttributionMarche;
use App\Models\ContenuSite;
use App\Models\MarchePublic;
use Illuminate\Database\Seeder;

class SitePublicSeeder extends Seeder
{
    public function run(): void
    {
        $descriptions = [
            'EST' => "Siège National & Entrepôt Central d'Owendo. Point de départ des approvisionnements vers l'intérieur du pays.",
            'G2-FRV' => "Antenne clé du Sud-Est. Réapprovisionnement régulier via la gare de Franceville.",
            'G3-LAM' => "Nœud central de transit et approvisionnement des structures hospitalières Albert Schweitzer et CHR Lambaréné.",
            'G4-MOU' => "Couverture du bassin Ngounié avec zones rurales à accès difficile en saison des pluies.",
            'G5-TCH' => "Zone Sud à forte contrainte logistique et télécom. Stock de sécurité de 60 jours recommandé.",
            'G6-MAK' => "Province isolée à l'Est. Nécessite une veille permanente sur les antipaludiques et vaccins.",
            'G7-KLM' => "Plateforme logistique adossée à l'axe ferroviaire de Lastourville.",
            'G8-POG' => "Capitale économique. Approvisionnement exclusif par voie maritime et rotations maritimes régulières.",
            'G9-OYE' => "Grand bassin septentrional frontalier. Forte demande en antibiotiques et solutés.",
        ];

        foreach ($descriptions as $code => $description) {
            AntenneProvinciale::where('code_antenne', $code)->update(['description' => $description]);
        }

        $articles = [
            ['ACT-01', "Réception de 45 tonnes de solutés massifs au Hub Central d'Owendo", '06 Septembre 2026', '2026-09-06', 'Logistique & Approvisionnement', 'bg-[#2E68B4] text-white', "Arrivée au port d'Owendo d'un convoi maritime prioritaire composé de chlorure de sodium, glucose et solutés de réanimation destinés aux CHU et hôpitaux régionaux.", "La Direction Générale de l'OPN a supervisé la réception de 45 tonnes métriques de solutés massifs. Ce lot stratégique permet de sécuriser 90 jours d'autonomie pour les provinces du Gabon. Le dispatching vers les antennes de l'intérieur du pays a débuté via le convoi ferroviaire Setrag et des camions sécurisés climatisés.", true, 1],
            ['ACT-02', 'Déploiement du système de traçabilité numérique dans la Ngounié et la Nyanga', '02 Septembre 2026', '2026-09-02', 'Transformation Digitale', 'bg-[#00A651] text-white', 'Les antennes de Mouila et Tchibanga sont désormais interconnectées en temps réel avec le Hub Central d\'Owendo pour le suivi FEFO des stocks.', "Dans le cadre du plan de modernisation, les pharmaciens des antennes provinciales de Mouila et Tchibanga ont été dotés de terminaux durcis connectés. Ce système permet la télétransmission instantanée des bordereaux de livraison et la détection prédictive des tensions de stock.", false, 2],
            ['ACT-03', 'Homologation de la nouvelle grille tarifaire des antipaludiques et antibiotiques', '28 Août 2026', '2026-08-28', 'Réglementation & Tarifs', 'bg-[#2E68B4] text-white', "Publication officielle du catalogue des prix plafonds garantis par l'État pour protéger le pouvoir d'achat des populations.", "En concertation avec le Ministère de la Santé et les partenaires institutionnels, l'OPN a harmonisé les prix des molécules prioritaires. Les tarifs sont affichés de manière transparente dans l'ensemble des pharmacies hospitalières et officines agréées.", false, 3],
            ['ACT-04', 'Audit de sécurité de la chaîne du froid dans les antennes provinciales', '20 Août 2026', '2026-08-20', 'Qualité & Sécurité', 'bg-[#00A651] text-white', 'Certification de conformité des chambres froides solaires 2°C–8°C garantissant l\'intégrité des vaccins et insulines.', "Une mission conjointe de la Direction Technique et d'experts biomédicaux a audité les installations de stockage des chefs-lieux de province. Les chambres froides équipées d'onduleurs solaires sont certifiées conformes aux normes OMS.", false, 4],
        ];

        foreach ($articles as [$code, $titre, $label, $date, $tag, $color, $resume, $contenu, $une, $ordre]) {
            Actualite::updateOrCreate(['code' => $code], [
                'titre' => $titre,
                'date_label' => $label,
                'date_publication' => $date,
                'tag' => $tag,
                'tag_color' => $color,
                'resume' => $resume,
                'contenu' => $contenu,
                'mis_en_avant' => $une,
                'publie' => true,
                'ordre' => $ordre,
            ]);
        }

        $marches = [
            ['MP-2026-001', "Avis N° 001/OPN/DG/DMP/2026", "Fourniture & Acheminement National de Médicaments Essentiels Génériques (DCI), Solutés Massifs et Formes Injectables Hospitalières", 'MEDICAMENTS', 'Médicaments & Formes Orales/Injectables', "Appel d'Offres International (AOI)", 'AOI', '10 Août 2026', '15 Octobre 2026 à 12h00', 3850000000, '77 000 000 FCFA (2%)', '500 000 FCFA', 'Soumissions Ouvertes', [
                ['num' => 'Lot 1', 'nom' => "Antipaludiques ACT & Produits d'urgence vitale", 'budget' => '1 200 000 000 FCFA'],
                ['num' => 'Lot 2', 'nom' => 'Antibiotiques majeurs, C3G et anti-infectieux injectables', 'budget' => '1 150 000 000 FCFA'],
                ['num' => 'Lot 3', 'nom' => 'Solutés massifs, électrolytes et perfusions hospitalières', 'budget' => '950 000 000 FCFA'],
                ['num' => 'Lot 4', 'nom' => "Analgésiques, anti-inflammatoires et anesthésiques d'urgence", 'budget' => '550 000 000 FCFA'],
            ], 'DAO_Medicaments_DCI_National_2026.pdf', "Accord-cadre d'approvisionnement national annuel pour le stock central de l'OPN et le réapprovisionnement des antennes provinciales.", "Laboratoires titulaires d'une AMM valide, certifiés BPF/GMP. Échantillons obligatoires pour contrôle CQ.", 'Direction Générale • Direction des Marchés Publics', true],
            ['MP-2026-002', "Avis N° 002/OPN/DG/DMP/2026", "Acquisition et Déploiement de Dispositifs Médico-Chirurgicaux, Matériel d'Anesthésie-Réanimation et Consommables de Bloc", 'DISPOSITIFS', 'Dispositifs Médicaux & Bloc', "Appel d'Offres National (AON)", 'AON', '18 Août 2026', '28 Septembre 2026 à 15h00', 920000000, '18 400 000 FCFA (2%)', '250 000 FCFA', 'Soumissions Ouvertes', [
                ['num' => 'Lot 1', 'nom' => "Sondes d'intubation, canules de Guedel et masques d'anesthésie", 'budget' => '340 000 000 FCFA'],
                ['num' => 'Lot 2', 'nom' => 'Cathéters, perfuseurs et aiguilles stériles', 'budget' => '310 000 000 FCFA'],
                ['num' => 'Lot 3', 'nom' => 'Sutures, compresses et pansements stériles', 'budget' => '270 000 000 FCFA'],
            ], 'DAO_Consommables_Bloc_2026.pdf', 'Fourniture de dispositifs médicaux certifiés CE / ISO 13485 destinés aux blocs opératoires des CHU et CHR.', 'Distributeurs et fabricants agréés. Fiches techniques et certificats de stérilité exigés.', 'Direction des Approvisionnements & Pharmacie Hospitalière', true],
            ['MP-2026-003', "Avis N° 003/OPN/DG/DMP/2026", "Fourniture, Installation et Qualification d'Équipements de Chaîne de Froid Médicale pour les Antennes Provinciales", 'EQUIPEMENTS', 'Équipements & Chaîne de Froid', "Appel d'Offres International (AOI)", 'AOI', '25 Août 2026', '30 Octobre 2026 à 11h00', 650000000, '13 000 000 FCFA (2%)', '300 000 FCFA', 'Soumissions Ouvertes', [
                ['num' => 'Lot 1', 'nom' => 'Chambres froides modulaires solaires 2°C–8°C', 'budget' => '420 000 000 FCFA'],
                ['num' => 'Lot 2', 'nom' => 'Armoires frigorifiques négatives pour vaccins', 'budget' => '140 000 000 FCFA'],
                ['num' => 'Lot 3', 'nom' => 'Maintenance préventive et formation des techniciens', 'budget' => '90 000 000 FCFA'],
            ], 'DAO_Equipements_Chaine_Froid_2026.pdf', 'Modernisation de la chaîne de froid des dépôts pharmaceutiques régionaux.', 'Équipements préqualifiés OMS PQS. Garantie constructeur minimum de 36 mois.', 'Direction Technique & Logistique OPN', true],
            ['MP-2026-004', "Avis N° 004/OPN/DG/DMP/2026", 'Prestations de Transport Sécurisé & Distribution Sous Température Dirigée', 'LOGISTIQUE', 'Logistique & Fret Sécurisé', "Appel d'Offres National (AON)", 'AON', '01 Septembre 2026', '10 Octobre 2026 à 14h00', 480000000, '9 600 000 FCFA (2%)', '200 000 FCFA', 'Soumissions Ouvertes', [
                ['num' => 'Lot 1', 'nom' => 'Acheminement routier axe Ouest-Sud', 'budget' => '190 000 000 FCFA'],
                ['num' => 'Lot 2', 'nom' => 'Acheminement ferroviaire et routier Centre-Est', 'budget' => '160 000 000 FCFA'],
                ['num' => 'Lot 3', 'nom' => 'Acheminement Nord et Maritime', 'budget' => '130 000 000 FCFA'],
            ], 'DAO_Logistique_Transport_Securise_2026.pdf', 'Sélection de transporteurs équipés de camions frigorifiques et de suivi GPS.', 'Flotte conforme aux Bonnes Pratiques de Distribution. Assurance fret obligatoire.', "Direction Logistique & Chaîne d'Approvisionnement", false],
            ['MP-2026-005', "Avis N° 005/OPN/DG/DMP/2026", "Fourniture d'Urgence de Tests de Diagnostic Rapide et Réactifs de Dépistage", 'DIAGNOSTIC', 'Diagnostic & Tests Rapides', 'Demande de Renseignements de Prix (DRP)', 'DRP', '03 Septembre 2026', '22 Septembre 2026 à 12h00', 310000000, '3 100 000 FCFA (1%)', '100 000 FCFA', 'Procédure Accélérée', [
                ['num' => 'Lot Unique', 'nom' => 'Kits TDR Malaria et tests combinés de dépistage', 'budget' => '310 000 000 FCFA'],
            ], 'DAO_TDR_Diagnostic_Urgence_2026.pdf', 'Réapprovisionnement des formations sanitaires publiques en tests de dépistage rapide.', 'Agrément OMS / CE-IVD. Délais de livraison inférieurs à 20 jours.', 'Direction des Programmes Spéciaux', false],
            ['MP-2026-006', "Avis N° 006/OPN/DG/DMP/2026", "Recrutement d'un Cabinet pour l'Audit et l'Accompagnement à la Certification ISO 9001:2015", 'SERVICES', 'Prestations Intellectuelles & Audit', "Appel à Manifestation d'Intérêt (AMI)", 'AMI', '15 Août 2026', '05 Novembre 2026 à 16h00', 75000000, '1 500 000 FCFA (2%)', '50 000 FCFA', 'Soumissions Ouvertes', [
                ['num' => 'Lot Unique', 'nom' => 'Audit à blanc, optimisation du SMQ et préparation à la certification', 'budget' => '75 000 000 FCFA'],
            ], 'TDR_Certification_ISO_OPN_2026.pdf', "Accompagnement de la Direction Générale vers la certification de gestion de la qualité.", "Cabinets ayant des références dans la certification d'organisations de santé publique.", 'Direction Générale • Cellule Qualité & Audit Interne', false],
            ['MP-2026-007', "Avis N° 007/OPN/DG/DMP/2026", "Renforcement des Dispositifs de Sécurité des Magasins d'Oloumi et Owendo", 'SERVICES', 'Infrastructures & Sécurité', "Appel d'Offres National (AON)", 'AON', '28 Août 2026', '18 Octobre 2026 à 15h00', 140000000, '2 800 000 FCFA (2%)', '100 000 FCFA', 'Soumissions Ouvertes', [
                ['num' => 'Lot 1', 'nom' => 'Vidéosurveillance haute définition et détection volumétrique', 'budget' => '85 000 000 FCFA'],
                ['num' => 'Lot 2', 'nom' => "Contrôle d'accès biométrique des zones de quarantaine", 'budget' => '55 000 000 FCFA'],
            ], 'DAO_Securite_Magasins_Centraux_2026.pdf', "Sécurisation physique et traçabilité des accès aux zones de stockage de médicaments.", 'Entreprises spécialisées agréées, expérience en sécurité de sites sensibles.', 'Direction de la Sécurité & Patrimoine OPN', false],
        ];

        foreach ($marches as $i => $row) {
            [$code, $ref, $titre, $cat, $catLabel, $type, $typeCode, $pub, $limite, $budget, $caution, $frais, $statutLabel, $lots, $dao, $desc, $cond, $autorite, $accueil] = $row;
            MarchePublic::updateOrCreate(['code' => $code], [
                'reference' => $ref,
                'titre' => $titre,
                'categorie' => $cat,
                'categorie_label' => $catLabel,
                'type' => $type,
                'type_code' => $typeCode,
                'date_publication_label' => $pub,
                'date_limite_label' => $limite,
                'budget' => $budget,
                'caution' => $caution,
                'frais_dao' => $frais,
                'statut' => 'EN_COURS',
                'statut_label' => $statutLabel,
                'allotissement' => $lots,
                'cahier_charges' => $dao,
                'description' => $desc,
                'conditions' => $cond,
                'autorite' => $autorite,
                'publie' => true,
                'afficher_accueil' => $accueil,
                'ordre' => $i + 1,
            ]);
        }

        $attributions = [
            ["Avis N° 022/OPN/DG/DMP/2025", 'Fourniture de solutés massifs et électrolytes pour le semestre 1-2026', 'Laboratoires MEDIS S.A.', 480000000, '15 Janvier 2026', '60 Jours', 'Approuvé par la DGCPMP (Visa N° 2026-084)'],
            ["Avis N° 024/OPN/DG/DMP/2025", 'Acquisition de 4 véhicules utilitaires 4x4 de supervision sanitaire provinciale', 'SOGAFRIC MOTORS GABON', 185000000, '28 Février 2026', '30 Jours', 'Approuvé par la DGCPMP (Visa N° 2026-119)'],
            ["Avis N° 026/OPN/DG/DMP/2025", 'Mise à niveau et redondance électrique par onduleurs industriels du Hub Owendo', 'SCHNEIDER ELECTRIC GABON', 92000000, '12 Avril 2026', '45 Jours', 'Approuvé par la DGCPMP (Visa N° 2026-204)'],
        ];

        foreach ($attributions as $row) {
            AttributionMarche::updateOrCreate(['reference' => $row[0]], [
                'objet' => $row[1],
                'attributaire' => $row[2],
                'montant' => $row[3],
                'date_attribution_label' => $row[4],
                'delai_execution' => $row[5],
                'statut_dgcpmp' => $row[6],
                'publie' => true,
            ]);
        }

        $this->contenu('hero', 'hero', 'hero', null, "Garantir la disponibilité permanente, la qualité certifiée et l'accessibilité tarifaire des médicaments essentiels pour toutes les structures sanitaires du Gabon.", null, 1);
        $this->contenu('dg', 'dg', 'dg', '« Le médicament pour tous, partout au Gabon »', null, [
            'nom' => 'Colonel Annie Josette POATY',
            'fonction' => "Directeur Général de l'Office Pharmaceutique National",
            'badge' => 'Direction Générale',
            'paragraphes' => [
                "L'Office Pharmaceutique National s'engage résolument dans la refondation de notre chaîne logistique de santé. Notre mission régalienne est de faire en sorte qu'aucun centre de santé, de Libreville à Makokou, de Port-Gentil à Tchibanga, ne connaisse de rupture sur les molécules vitales.",
                "Grâce à la numérisation des approvisionnements, au calcul rigoureux de la CMM et à la rigueur de notre gestion, nous garantissons l'intégrité de la chaîne du froid, la traçabilité par lot (FEFO) et la stricte application des prix réglementés en Franc CFA.",
            ],
        ], 1);

        $missions = [
            ['mission-1', 'Approvisionnement Centralisé', "Achat groupé et négociation de prix préférentiels auprès des laboratoires internationaux pour garantir les meilleurs tarifs.", 'Building2', 'border-l-4 border-l-[#2E68B4]'],
            ['mission-2', 'Traçabilité & Qualité FEFO', "Contrôle strict des lots, surveillance de la chaîne du froid 2°C–8°C et conformité aux standards de l'OMS.", 'ShieldCheck', 'border-l-4 border-l-[#00A651]'],
            ['mission-3', 'Distribution territoriale', 'Logistique multimodale (Route RN1/RN2, Rail Setrag, Maritime) reliant le Hub d\'Owendo aux antennes provinciales.', 'Truck', 'border-l-4 border-l-[#FFDE16]'],
            ['mission-4', 'Tarifs Réglementés & Accessibilité', 'Application stricte des prix encadrés en FCFA pour que chaque citoyen gabonais accède aux soins essentiels.', 'Pill', 'border-l-4 border-l-[#2E68B4]'],
        ];
        foreach ($missions as $i => [$cle, $titre, $contenu, $icone, $accent]) {
            $this->contenu('mission', $cle, $cle, $titre, $contenu, ['icone' => $icone, 'accent' => $accent], $i + 1);
        }

        $this->contenu('contact', 'contact', 'contact', 'Siège National & Hub Central', null, [
            'nom' => 'Office Pharmaceutique National (OPN)',
            'lignes' => [
                'Office Pharmaceutique National (OPN)',
                "Zone Portuaire & Logistique d'Owendo / Libreville",
                'BP 3838 – République Gabonaise',
            ],
            'telephone' => '(+241) 074 35 79 29',
            'telephoneAlt' => '011 70 20 00',
            'telephoneHref' => '+241074357929',
            'email' => 'contact@opn-gabon.com',
            'horaires' => 'Lun - Ven : 07h30 – 15h30',
            'horairesCourt' => 'Lun–Ven 07h30 – 15h30',
            'urgenceTitre' => "Ligne d'Urgence Sanitaire 24h/24",
            'urgenceTexte' => "En cas de rupture critique dans un hôpital provincial ou d'urgence épidémique, contactez immédiatement le Centre Opérationnel des Urgences.",
            'urgence1411' => '1411',
            'urgenceTel' => '(+241) 077 92 11 05',
            'urgenceTelHref' => '+241077921105',
            'adresseCourte' => 'Hub Owendo · B.P. 3838 — Libreville',
            'commercialLignes' => '062 26 17 32 · 062 26 14 75 · 062 26 30 39',
            'emailCommercial' => 'commercial@opn-gabon.com',
            'adresseOloumi' => "Zone Industrielle d'Oloumi · B.P. 295",
            'siegeOloumi' => "Zone Industrielle d'Oloumi, Libreville • Boîte Postale 295 • Hub Logistique et Magasins Centraux.",
            'siteWeb' => 'https://www.opn-gabon.com',
            'siteLabel' => 'www.opn-gabon.com',
            'baseline' => "Centrale nationale d'achat pharmaceutique : disponibilité, qualité et accessibilité des médicaments essentiels.",
            'marchesEmail' => 'contact@opn-gabon.com',
            'marchesTel' => '+241 01 76 55 07',
            'marchesTelHref' => '+24101765507',
        ], 1);

        $this->contenu('apropos_intro', 'apropos-intro', 'apropos_intro', "À Propos de l'Office Pharmaceutique National (OPN)", "Société d'État investie d'une mission de service public à caractère sanitaire et social, sous la tutelle du Ministre en charge de la Santé.", null, 1);

        $cadres = [
            ['cadre-1', 'Fondement Légal & Création', "Créé par l'Ordonnance n°1/95 du 14 janvier 1995, modifiée par l'Ordonnance n°008/PR/2021 (art. 76) et ratifiée par la Loi n°007/2021 du 12 juillet 2021."],
            ['cadre-2', 'Statut Juridique & Gouvernance', "Société d'État (Loi n°20/2005, art. 44) dotée de l'autonomie financière et administrative, régie par la comptabilité publique et l'Acte Uniforme OHADA."],
            ['cadre-3', 'Siège Social Officiel', "Zone Industrielle d'Oloumi, Libreville • Boîte Postale 295 • Hub Logistique et Magasins Centraux."],
        ];
        foreach ($cadres as $i => [$cle, $titre, $contenu]) {
            $this->contenu('apropos_cadre', $cle, $cle, $titre, $contenu, null, $i + 1);
        }

        $visions = [
            ['vision-1', 'Zéro Rupture Médicamenteuse', "Mettre fin aux manques de médicaments et autres produits de santé essentiels dans toutes les formations sanitaires du Gabon d'ici à 2030.", ['kicker' => 'Vision Stratégique 2030', 'note' => 'Horizon Gabon 2030', 'theme' => 'green']],
            ['vision-2', 'Référence Panafricaine', "Se hisser aux standards des meilleures centrales d'achat africaines et faire de l'OPN un véritable pôle d'excellence en approvisionnement au Gabon.", ['kicker' => "Ambition d'Excellence", 'note' => 'Standards Internationaux', 'theme' => 'blue']],
            ['vision-3', 'Accessibilité & Moindre Coût', "Assurer l'approvisionnement des MAPS sur toute l'étendue du territoire national et garantir leur accessibilité à moindre coût aux populations.", ['kicker' => 'Mission de Service Public', 'note' => 'Territoire national couvert', 'theme' => 'navy']],
        ];
        foreach ($visions as $i => [$cle, $titre, $contenu, $meta]) {
            $this->contenu('apropos_vision', $cle, $cle, $titre, $contenu, $meta, $i + 1);
        }

        $objectifs = [
            ['obj-1', 'Qualité & Moindre Coût', 'Médicament de qualité, au bon endroit, au bon moment et au juste prix, dans le respect de la Liste Nationale des Médicaments Essentiels (MEG).'],
            ['obj-2', "Chaîne d'Approvisionnement", "Développement et fiabilisation d'un réseau logistique national sécurisé reliant Libreville/Owendo aux antennes régionales."],
            ['obj-3', 'Renforcement des Capacités', 'Formation continue et accompagnement technique des gestionnaires hospitaliers de médicaments dans toutes les formations sanitaires.'],
            ['obj-4', 'Conseil au Gouvernement', "Assistance de l'État dans la formulation des orientations et la prise de décisions pour la politique pharmaceutique nationale."],
        ];
        foreach ($objectifs as $i => [$cle, $titre, $contenu]) {
            $this->contenu('apropos_objectif', $cle, $cle, $titre, $contenu, ['numero' => str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)], $i + 1);
        }

        $activites = [
            ['act-1', "L'Achat Stratégique & Marchés Publics", "Sélection rigoureuse selon le rapport Qualité/Prix : collecte des besoins, quantification CMM, passation conforme au Code des Marchés Publics, attribution et suivi d'acheminement.", ['badge' => 'Activité 1', 'pied' => 'Respect strict du Code des Marchés Publics', 'icone' => 'Building2']],
            ['act-2', 'Stockage Sécurisé & Logiciel SAGE X3', 'Magasins de gros et magasin dédié aux programmes. Gestion informatisée par lot et emplacement, contrôle de conformité à réception, chaîne du froid et inventaires contradictoires.', ['badge' => 'Activité 2', 'pied' => 'Traçabilité FEFO informatisée', 'icone' => 'Layers']],
            ['act-3', 'Distribution aux Antennes Provinciales', 'Réseau d\'antennes régionales approvisionnées. Clients publics (CHU, CHR, HD, hôpitaux, dispensaires) et privés (cliniques, grossistes, pharmacies).', ['badge' => 'Activité 3', 'pied' => 'Processus cadencé : devis, colisage, livraison', 'icone' => 'Truck']],
            ['act-4', 'Démarche Qualité & Contrôle ANMAPS', "Conformité aux normes OMS. Préqualification des couples produit/fabricant et contrôles qualité menés avec l'Agence Nationale du Médicament et Autres Produits de Santé (ANMAPS).", ['badge' => 'Activité 4', 'pied' => 'Préqualification et normes OMS', 'icone' => 'ShieldCheck']],
            ['act-5', 'Appui & Accompagnement des Formations Sanitaires', "Assistance technique et planification des besoins, substitution DCI équivalente, sensibilisation à la conservation, coordination avec les équipes médicales.", ['badge' => 'Activité 5', 'pied' => "Disponibilité continue sur l'ensemble du territoire", 'icone' => 'HeartPulse']],
        ];
        foreach ($activites as $i => [$cle, $titre, $contenu, $meta]) {
            $this->contenu('apropos_activite', $cle, $cle, $titre, $contenu, $meta, $i + 1);
        }
    }

    private function contenu(string $groupe, string $cle, string $unused, ?string $titre, ?string $contenu, ?array $meta, int $ordre, ?string $resume = null): void
    {
        unset($unused);
        ContenuSite::updateOrCreate(['cle' => $cle], [
            'groupe' => $groupe,
            'titre' => $titre,
            'resume' => $resume,
            'contenu' => $contenu,
            'meta' => $meta,
            'ordre' => $ordre,
            'publie' => true,
        ]);
    }
}
