<?php

namespace Database\Seeders;

use App\Models\Direction;
use App\Models\DirectionModule;
use App\Models\NiveauAcces;
use Illuminate\Database\Seeder;

class DirectionSeeder extends Seeder
{
    public function run(): void
    {
        // Directions avec leurs modules et niveaux d'accès
        $directionsData = [
            [
                'code' => 'DG',
                'nom' => 'Direction Générale',
                'description' => 'Supervision stratégique et décision finale',
                'couleur' => '#1e3a8a',
                'icone' => 'Crown',
                'ordre' => 1,
                'modules' => [
                    ['code' => 'dashboard_strategique', 'nom' => 'Dashboard Stratégique', 'icone' => 'LayoutDashboard', 'ordre' => 1],
                    ['code' => 'supervision_directions', 'nom' => 'Supervision Directions', 'icone' => 'Building2', 'ordre' => 2],
                    ['code' => 'visas_dg', 'nom' => 'Visas & Signatures', 'icone' => 'FileCheck', 'ordre' => 3],
                    ['code' => 'centre_decision', 'nom' => 'Centre de Décision (COD)', 'icone' => 'AlertTriangle', 'ordre' => 4],
                    ['code' => 'rapports_audit', 'nom' => 'Rapports & Audit', 'icone' => 'FileText', 'ordre' => 5],
                    ['code' => 'superadmin', 'nom' => 'Administration Système', 'icone' => 'ShieldAlert', 'ordre' => 6],
                ],
                'niveaux' => [
                    ['code' => 'DG_DIRECTEUR', 'nom' => 'Directeur Général', 'niveau_hierarchie' => 1, 'permissions' => ['all']],
                    ['code' => 'DG_CABINET', 'nom' => 'Cabinet DG', 'niveau_hierarchie' => 2, 'permissions' => ['read', 'prepare', 'sign']],
                    ['code' => 'DG_ASSISTANT', 'nom' => 'Assistant DG', 'niveau_hierarchie' => 3, 'permissions' => ['read', 'agenda']],
                ],
            ],
            [
                'code' => 'APPRO',
                'nom' => 'Direction des Approvisionnements',
                'description' => 'Planification, stocks et allocation FEFO',
                'couleur' => '#059669',
                'icone' => 'Package',
                'ordre' => 2,
                'modules' => [
                    ['code' => 'planification_cmm', 'nom' => 'Planification & CMM', 'icone' => 'TrendingUp', 'ordre' => 1],
                    ['code' => 'wms_central', 'nom' => 'Hub Central WMS Owendo', 'icone' => 'Warehouse', 'ordre' => 2],
                    ['code' => 'allocation_fefo', 'nom' => 'Allocation FEFO', 'icone' => 'Layers', 'ordre' => 3],
                    ['code' => 'supervision_antennes', 'nom' => 'Supervision 9 Antennes', 'icone' => 'Map', 'ordre' => 4],
                    ['code' => 'reserves_strategiques', 'nom' => 'Réserves Stratégiques (RSSE)', 'icone' => 'Shield', 'ordre' => 5],
                ],
                'niveaux' => [
                    ['code' => 'APPRO_DIRECTEUR', 'nom' => 'Directeur Appro', 'niveau_hierarchie' => 1, 'permissions' => ['validate_national', 'transfer_achats', 'allocate_fefo', 'all_appro']],
                    ['code' => 'APPRO_COLLABORATEUR', 'nom' => 'Collaborateur Appro', 'niveau_hierarchie' => 2, 'permissions' => ['instruct', 'process', 'reject']],
                    ['code' => 'APPRO_PLANIFICATEUR', 'nom' => 'Planificateur', 'niveau_hierarchie' => 3, 'permissions' => ['cmm', 'simulate', 'read']],
                    ['code' => 'APPRO_STOCK', 'nom' => 'Gestionnaire Stock', 'niveau_hierarchie' => 4, 'permissions' => ['wms', 'inventory', 'read']],
                ],
            ],
            [
                'code' => 'DAF',
                'nom' => 'Direction Administrative et Financière',
                'description' => 'Finance, budget et recouvrement',
                'couleur' => '#7c3aed',
                'icone' => 'Landmark',
                'ordre' => 3,
                'modules' => [
                    ['code' => 'facturation', 'nom' => 'Facturation & Recouvrement', 'icone' => 'Receipt', 'ordre' => 1],
                    ['code' => 'tresor_public', 'nom' => 'Intégration Trésor Public', 'icone' => 'Building', 'ordre' => 2],
                    ['code' => 'budget_engagements', 'nom' => 'Budget & Engagements', 'icone' => 'Calculator', 'ordre' => 3],
                    ['code' => 'comptabilite', 'nom' => 'Comptabilité Fournisseurs', 'icone' => 'FileSpreadsheet', 'ordre' => 4],
                    ['code' => 'paie_rh', 'nom' => 'Paie & RH', 'icone' => 'Users', 'ordre' => 5],
                ],
                'niveaux' => [
                    ['code' => 'DAF_DIRECTEUR', 'nom' => 'Directeur Financier', 'niveau_hierarchie' => 1, 'permissions' => ['visa_budget', 'validate_engagement', 'all_finance']],
                    ['code' => 'DAF_COMPTABLE', 'nom' => 'Comptable Principal', 'niveau_hierarchie' => 2, 'permissions' => ['reconciliation', 'lettrage', 'accounting']],
                    ['code' => 'DAF_PAIE', 'nom' => 'Gestionnaire Paie', 'niveau_hierarchie' => 3, 'permissions' => ['payroll', 'primes', 'read']],
                    ['code' => 'DAF_RECOUVREMENT', 'nom' => 'Agent Recouvrement', 'niveau_hierarchie' => 4, 'permissions' => ['invoices', 'collection', 'read']],
                ],
            ],
            [
                'code' => 'LOGISTIQUE',
                'nom' => 'Direction de la Logistique',
                'description' => 'Distribution, flotte et maintenance',
                'couleur' => '#ea580c',
                'icone' => 'Truck',
                'ordre' => 4,
                'modules' => [
                    ['code' => 'distribution', 'nom' => 'Distribution Dernier Km', 'icone' => 'Route', 'ordre' => 1],
                    ['code' => 'flotte', 'nom' => 'Flotte & Convois', 'icone' => 'Car', 'ordre' => 2],
                    ['code' => 'gmao', 'nom' => 'GMAO Maintenance', 'icone' => 'Wrench', 'ordre' => 3],
                    ['code' => 'chaine_froid', 'nom' => 'Chaîne du Froid IoT', 'icone' => 'Thermometer', 'ordre' => 4],
                    ['code' => 'pistes', 'nom' => 'Pistes & Transport', 'icone' => 'MapPin', 'ordre' => 5],
                ],
                'niveaux' => [
                    ['code' => 'LOG_DIRECTEUR', 'nom' => 'Directeur Logistique', 'niveau_hierarchie' => 1, 'permissions' => ['supervise', 'plan', 'all_logistics']],
                    ['code' => 'LOG_FLOTTE', 'nom' => 'Gestionnaire Flotte', 'niveau_hierarchie' => 2, 'permissions' => ['dispatch', 'plan_routes', 'read']],
                    ['code' => 'LOG_CONVOI', 'nom' => 'Chef Convoi', 'niveau_hierarchie' => 3, 'permissions' => ['escort', 'security', 'read']],
                    ['code' => 'LOG_CONVOYEUR', 'nom' => 'Convoyeur', 'niveau_hierarchie' => 4, 'permissions' => ['execute', 'scan', 'read']],
                ],
            ],
            [
                'code' => 'QUALITE',
                'nom' => 'Direction de la Qualité Pharmaceutique',
                'description' => 'Qualité, vigilance et conformité',
                'couleur' => '#0891b2',
                'icone' => 'Award',
                'ordre' => 5,
                'modules' => [
                    ['code' => 'qualite_conformite', 'nom' => 'Qualité & Conformité LNCM', 'icone' => 'CheckCircle', 'ordre' => 1],
                    ['code' => 'pharmacovigilance', 'nom' => 'Pharmacovigilance', 'icone' => 'Activity', 'ordre' => 2],
                    ['code' => 'controle_qualite', 'nom' => 'Contrôle Qualité COA', 'icone' => 'Microscope', 'ordre' => 3],
                    ['code' => 'dmp', 'nom' => 'Dossier Médical Patient', 'icone' => 'FileMedical', 'ordre' => 4],
                    ['code' => 'homologations', 'nom' => 'Homologations & Retraits', 'icone' => 'Ban', 'ordre' => 5],
                ],
                'niveaux' => [
                    ['code' => 'QUAL_CHEF', 'nom' => 'Pharmacien Chef National', 'niveau_hierarchie' => 1, 'permissions' => ['supervise_quality', 'validate', 'all_quality']],
                    ['code' => 'QUAL_RESPONSABLE', 'nom' => 'Responsable Qualité', 'niveau_hierarchie' => 2, 'permissions' => ['control', 'vigilance', 'read']],
                    ['code' => 'QUAL_TECHNICIEN', 'nom' => 'Technicien Labo', 'niveau_hierarchie' => 3, 'permissions' => ['analyze', 'test', 'read']],
                    ['code' => 'QUAL_INSPECTEUR', 'nom' => 'Inspecteur', 'niveau_hierarchie' => 4, 'permissions' => ['audit', 'inspect', 'read']],
                ],
            ],
            [
                'code' => 'ACHATS',
                'nom' => 'Direction des Achats Internationaux',
                'description' => 'Marchés, fournisseurs et importations',
                'couleur' => '#ca8a04',
                'icone' => 'Globe',
                'ordre' => 6,
                'modules' => [
                    ['code' => 'marches', 'nom' => 'Marchés & Appels d\'Offres', 'icone' => 'Gavel', 'ordre' => 1],
                    ['code' => 'fournisseurs', 'nom' => 'Panel Fournisseurs', 'icone' => 'Users', 'ordre' => 2],
                    ['code' => 'incoterms', 'nom' => 'Incoterms & Douanes (BAE)', 'icone' => 'Ship', 'ordre' => 3],
                    ['code' => 'contrats', 'nom' => 'Contrats & Négociation', 'icone' => 'FileText', 'ordre' => 4],
                    ['code' => 'commandes', 'nom' => 'Suivi Commandes Fournisseurs', 'icone' => 'ShoppingCart', 'ordre' => 5],
                ],
                'niveaux' => [
                    ['code' => 'ACH_DIRECTEUR', 'nom' => 'Directeur Achats', 'niveau_hierarchie' => 1, 'permissions' => ['decide_markets', 'sign_contracts', 'all_purchases']],
                    ['code' => 'ACH_CHARGE', 'nom' => 'Chargé Achats', 'niveau_hierarchie' => 2, 'permissions' => ['manage_suppliers', 'follow_orders', 'read']],
                    ['code' => 'ACH_JURIDIQUE', 'nom' => 'Juridique Marchés', 'niveau_hierarchie' => 3, 'permissions' => ['contracts', 'aoi', 'read']],
                    ['code' => 'ACH_ASSISTANT', 'nom' => 'Assistant Achats', 'niveau_hierarchie' => 4, 'permissions' => ['data_entry', 'follow', 'read']],
                ],
            ],
            [
                'code' => 'RH',
                'nom' => 'Direction des Ressources Humaines',
                'description' => 'Gestion du personnel et carrières',
                'couleur' => '#db2777',
                'icone' => 'UserCheck',
                'ordre' => 7,
                'modules' => [
                    ['code' => 'postes', 'nom' => 'Gestion des Postes', 'icone' => 'Briefcase', 'ordre' => 1],
                    ['code' => 'recrutement', 'nom' => 'Recrutement & Intégration', 'icone' => 'UserPlus', 'ordre' => 2],
                    ['code' => 'formation', 'nom' => 'Formation & Certification', 'icone' => 'GraduationCap', 'ordre' => 3],
                    ['code' => 'evaluation', 'nom' => 'Évaluation Performance', 'icone' => 'BarChart', 'ordre' => 4],
                    ['code' => 'admin_personnel', 'nom' => 'Administration Personnel', 'icone' => 'Users', 'ordre' => 5],
                ],
                'niveaux' => [
                    ['code' => 'RH_DRH', 'nom' => 'DRH', 'niveau_hierarchie' => 1, 'permissions' => ['decide_hr', 'all_hr']],
                    ['code' => 'RH_CARRIERES', 'nom' => 'Gestionnaire Carrières', 'niveau_hierarchie' => 2, 'permissions' => ['manage_positions', 'recruit', 'read']],
                    ['code' => 'RH_FORMATION', 'nom' => 'Responsable Formation', 'niveau_hierarchie' => 3, 'permissions' => ['training', 'certify', 'read']],
                    ['code' => 'RH_ASSISTANT', 'nom' => 'Assistant RH', 'niveau_hierarchie' => 4, 'permissions' => ['admin_daily', 'read']],
                ],
            ],
            [
                'code' => 'COMMUNICATION',
                'nom' => 'Direction de la Communication',
                'description' => 'Portail public et relations médias',
                'couleur' => '#6366f1',
                'icone' => 'Megaphone',
                'ordre' => 8,
                'modules' => [
                    ['code' => 'portail_cms', 'nom' => 'Portail Public CMS', 'icone' => 'Globe', 'ordre' => 1],
                    ['code' => 'presse', 'nom' => 'Relations Presse', 'icone' => 'Newspaper', 'ordre' => 2],
                    ['code' => 'appels_offres', 'nom' => 'Appels d\'Offres Publics', 'icone' => 'Scroll', 'ordre' => 3],
                    ['code' => 'newsletter', 'nom' => 'Newsletter & Alertes', 'icone' => 'Mail', 'ordre' => 4],
                    ['code' => 'multimedia', 'nom' => 'Gestion Contenu Multimédia', 'icone' => 'Video', 'ordre' => 5],
                ],
                'niveaux' => [
                    ['code' => 'COM_DIRECTEUR', 'nom' => 'Directeur Communication', 'niveau_hierarchie' => 1, 'permissions' => ['strategy', 'all_com']],
                    ['code' => 'COM_EDITORIAL', 'nom' => 'Responsable Editorial', 'niveau_hierarchie' => 2, 'permissions' => ['content', 'publish', 'read']],
                    ['code' => 'COM_WEBMASTER', 'nom' => 'Webmaster', 'niveau_hierarchie' => 3, 'permissions' => ['cms', 'portal', 'read']],
                    ['code' => 'COM_PRESSE', 'nom' => 'Chargé Presse', 'niveau_hierarchie' => 4, 'permissions' => ['media', 'press', 'read']],
                ],
            ],
            [
                'code' => 'ANTENNES',
                'nom' => 'Direction des Antennes Provinciales',
                'description' => '9 Antennes régionales',
                'couleur' => '#16a34a',
                'icone' => 'MapPin',
                'ordre' => 9,
                'modules' => [
                    ['code' => 'commandes_antenne', 'nom' => 'Commandes & Stocks Antenne', 'icone' => 'Package', 'ordre' => 1],
                    ['code' => 'distribution_locale', 'nom' => 'Distribution Locale', 'icone' => 'Truck', 'ordre' => 2],
                    ['code' => 'facturation_regionale', 'nom' => 'Facturation Régionale', 'icone' => 'Receipt', 'ordre' => 3],
                    ['code' => 'inventaires', 'nom' => 'Inventaires Provinciaux', 'icone' => 'ClipboardCheck', 'ordre' => 4],
                    ['code' => 'liaison_siege', 'nom' => 'Liaison avec Siège', 'icone' => 'MessageSquare', 'ordre' => 5],
                ],
                'niveaux' => [
                    ['code' => 'ANT_CHEF', 'nom' => 'Chef Antenne', 'niveau_hierarchie' => 1, 'permissions' => ['validate_local', 'send_hq', 'all_antenna']],
                    ['code' => 'ANT_PHARMA', 'nom' => 'Pharmacien Chef Antenne', 'niveau_hierarchie' => 2, 'permissions' => ['pharmacy_review', 'read']],
                    ['code' => 'ANT_STOCK', 'nom' => 'Gestionnaire Stock', 'niveau_hierarchie' => 3, 'permissions' => ['receive', 'manage_stock', 'read']],
                    ['code' => 'ANT_FACTURATION', 'nom' => 'Agent Facturation', 'niveau_hierarchie' => 4, 'permissions' => ['invoices_local', 'read']],
                ],
            ],
            [
                'code' => 'GMAO',
                'nom' => 'Direction Maintenance Biomédicale',
                'description' => 'Maintenance et équipements',
                'couleur' => '#0891b2',
                'icone' => 'Wrench',
                'ordre' => 10,
                'modules' => [
                    ['code' => 'gmao_main', 'nom' => 'GMAO & Maintenance', 'icone' => 'Wrench', 'ordre' => 1],
                    ['code' => 'chaine_froid_gmao', 'nom' => 'Chaîne du Froid', 'icone' => 'Thermometer', 'ordre' => 2],
                    ['code' => 'groupes_electrogenes', 'nom' => 'Groupes Électrogènes', 'icone' => 'Zap', 'ordre' => 3],
                    ['code' => 'bons_intervention', 'nom' => 'Bons d\'Intervention', 'icone' => 'Clipboard', 'ordre' => 4],
                    ['code' => 'metrologie', 'nom' => 'Métrologie', 'icone' => 'Ruler', 'ordre' => 5],
                ],
                'niveaux' => [
                    ['code' => 'GMAO_RESPONSABLE', 'nom' => 'Responsable Maintenance', 'niveau_hierarchie' => 1, 'permissions' => ['supervise_gmao', 'all_maintenance']],
                    ['code' => 'GMAO_FRIGORISTE', 'nom' => 'Technicien Frigoriste', 'niveau_hierarchie' => 2, 'permissions' => ['cold_rooms', 'maintenance', 'read']],
                    ['code' => 'GMAO_BIOMEDICAL', 'nom' => 'Technicien Biomédical', 'niveau_hierarchie' => 3, 'permissions' => ['equipment', 'maintenance', 'read']],
                    ['code' => 'GMAO_ELECTRICIEN', 'nom' => 'Électricien', 'niveau_hierarchie' => 4, 'permissions' => ['generators', 'maintenance', 'read']],
                ],
            ],
            [
                'code' => 'URGENCES',
                'nom' => 'Direction des Urgences Sanitaires (COD)',
                'description' => 'Centre Opérationnel de Décision',
                'couleur' => '#dc2626',
                'icone' => 'AlertTriangle',
                'ordre' => 11,
                'modules' => [
                    ['code' => 'centre_decision', 'nom' => 'Centre Opérationnel (COD)', 'icone' => 'Command', 'ordre' => 1],
                    ['code' => 'reserves_urgence', 'nom' => 'Réserves Stratégiques', 'icone' => 'Shield', 'ordre' => 2],
                    ['code' => 'deploiement', 'nom' => 'Déploiement Kits Urgence', 'icone' => 'Package', 'ordre' => 3],
                    ['code' => 'alertes', 'nom' => 'Alertes Sanitaires', 'icone' => 'Bell', 'ordre' => 4],
                    ['code' => 'coordination', 'nom' => 'Coordination Crise', 'icone' => 'Users', 'ordre' => 5],
                ],
                'niveaux' => [
                    ['code' => 'URG_COORDINATEUR', 'nom' => 'Coordinateur Urgences', 'niveau_hierarchie' => 1, 'permissions' => ['deploy', 'decide_emergency', 'all_emergency']],
                    ['code' => 'URG_OFFICIER', 'nom' => 'Officier COD', 'niveau_hierarchie' => 2, 'permissions' => ['follow_ops', 'read']],
                    ['code' => 'URG_MEDECIN', 'nom' => 'Médecin Urgentiste', 'niveau_hierarchie' => 3, 'permissions' => ['medical_advice', 'read']],
                    ['code' => 'URG_LOGISTICIEN', 'nom' => 'Logisticien Urgence', 'niveau_hierarchie' => 4, 'permissions' => ['kits', 'convoys', 'read']],
                ],
            ],
            [
                'code' => 'PORTAIL',
                'nom' => 'Direction du Portail Citoyen',
                'description' => 'Portail public et transparence',
                'couleur' => '#f97316',
                'icone' => 'Globe',
                'ordre' => 12,
                'modules' => [
                    ['code' => 'portail_public', 'nom' => 'Portail Public', 'icone' => 'Globe', 'ordre' => 1],
                    ['code' => 'mercuriale', 'nom' => 'Mercuriale Officielle', 'icone' => 'List', 'ordre' => 2],
                    ['code' => 'pharmacies_garde', 'nom' => 'Pharmacies de Garde', 'icone' => 'Crosshair', 'ordre' => 3],
                    ['code' => 'signalement', 'nom' => 'Signalement Anti-Fraude', 'icone' => 'ShieldAlert', 'ordre' => 4],
                    ['code' => 'transparence', 'nom' => 'Transparence Tarifs', 'icone' => 'Eye', 'ordre' => 5],
                ],
                'niveaux' => [
                    ['code' => 'POR_DIRECTEUR', 'nom' => 'Directeur Portail', 'niveau_hierarchie' => 1, 'permissions' => ['supervise_public', 'all_portal']],
                    ['code' => 'POR_MODERATEUR', 'nom' => 'Modérateur', 'niveau_hierarchie' => 2, 'permissions' => ['moderate', 'reports', 'read']],
                    ['code' => 'POR_SUPPORT', 'nom' => 'Support Citoyen', 'niveau_hierarchie' => 3, 'permissions' => ['support', 'read']],
                    ['code' => 'POR_DATA', 'nom' => 'Data Manager', 'niveau_hierarchie' => 4, 'permissions' => ['data_public', 'read']],
                ],
            ],
            [
                'code' => 'SUPERADMIN',
                'nom' => 'SuperAdmin (SI)',
                'description' => 'Administration système et sécurité',
                'couleur' => '#1f2937',
                'icone' => 'ShieldAlert',
                'ordre' => 13,
                'modules' => [
                    ['code' => 'admin_system', 'nom' => 'Administration Système', 'icone' => 'Server', 'ordre' => 1],
                    ['code' => 'gestion_users', 'nom' => 'Gestion Utilisateurs Globale', 'icone' => 'Users', 'ordre' => 2],
                    ['code' => 'securite', 'nom' => 'Sécurité & Audit', 'icone' => 'Shield', 'ordre' => 3],
                    ['code' => 'helpdesk', 'nom' => 'Helpdesk IT', 'icone' => 'Headphones', 'ordre' => 4],
                    ['code' => 'configuration', 'nom' => 'Configuration Globale', 'icone' => 'Settings', 'ordre' => 5],
                ],
                'niveaux' => [
                    ['code' => 'SA_SUPERADMIN', 'nom' => 'SuperAdmin', 'niveau_hierarchie' => 1, 'permissions' => ['all_system', 'root']],
                    ['code' => 'SA_ADMIN', 'nom' => 'Admin IT', 'niveau_hierarchie' => 2, 'permissions' => ['support', 'admin', 'read']],
                    ['code' => 'SA_SECURITE', 'nom' => 'Admin Sécurité', 'niveau_hierarchie' => 3, 'permissions' => ['audit', 'security', 'read']],
                    ['code' => 'SA_HELPDESK', 'nom' => 'Helpdesk', 'niveau_hierarchie' => 4, 'permissions' => ['support_l1', 'read']],
                ],
            ],
        ];

        foreach ($directionsData as $directionData) {
            $direction = Direction::create([
                'code' => $directionData['code'],
                'nom' => $directionData['nom'],
                'description' => $directionData['description'],
                'couleur' => $directionData['couleur'],
                'icone' => $directionData['icone'],
                'ordre' => $directionData['ordre'],
                'actif' => true,
            ]);

            // Créer les modules
            $moduleIds = [];
            foreach ($directionData['modules'] as $moduleData) {
                $module = DirectionModule::create([
                    'direction_id' => $direction->id,
                    'code' => $moduleData['code'],
                    'nom' => $moduleData['nom'],
                    'icone' => $moduleData['icone'],
                    'ordre' => $moduleData['ordre'],
                    'actif' => true,
                ]);
                $moduleIds[] = $module->id;
            }

            // Créer les niveaux d'accès
            foreach ($directionData['niveaux'] as $niveauData) {
                $niveau = NiveauAcces::create([
                    'direction_id' => $direction->id,
                    'code' => $niveauData['code'],
                    'nom' => $niveauData['nom'],
                    'niveau_hierarchie' => $niveauData['niveau_hierarchie'],
                    'permissions' => $niveauData['permissions'],
                    'actif' => true,
                ]);

                // Attacher tous les modules au niveau (par défaut)
                $niveau->modules()->attach($moduleIds);
            }
        }

        $this->command->info('Directions, modules et niveaux d\'accès créés avec succès.');
    }
}
