<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Direction;
use App\Models\NiveauAcces;
use Illuminate\Database\Seeder;

class UpdateUserDirectionsSeeder extends Seeder
{
    public function run(): void
    {
        // Mapping des rôles vers directions et niveaux d'accès
        $roleMapping = [
            // Direction Générale
            'Directeur Général' => ['direction' => 'DG', 'niveau' => 'DG_DIRECTEUR'],
            
            // Direction Approvisionnements
            'Directrice des Approvisionnements Nationaux' => ['direction' => 'APPRO', 'niveau' => 'APPRO_DIRECTEUR'],
            'Collaboratrice Approvisionnements' => ['direction' => 'APPRO', 'niveau' => 'APPRO_COLLABORATEUR'],
            'Collaborateur Approvisionnements' => ['direction' => 'APPRO', 'niveau' => 'APPRO_COLLABORATEUR'],
            
            // Direction Administrative et Financière
            'Directrice Administrative & Financière' => ['direction' => 'DAF', 'niveau' => 'DAF_DIRECTEUR'],
            
            // Direction des Opérations
            'Directeur des Opérations & Pilotage' => ['direction' => 'DG', 'niveau' => 'DG_CABINET'],
            
            // Direction Logistique
            'Gestionnaire Logistique & Flotte Convois' => ['direction' => 'LOGISTIQUE', 'niveau' => 'LOG_DIRECTEUR'],
            'Chef de Convoi Sécurisé & Sûreté' => ['direction' => 'LOGISTIQUE', 'niveau' => 'LOG_CONVOI'],
            'Convoyeur Opérationnel de Fret Pharmaceutique' => ['direction' => 'LOGISTIQUE', 'niveau' => 'LOG_CONVOYEUR'],
            
            // Direction Qualité
            'Pharmacien Chef National' => ['direction' => 'QUALITE', 'niveau' => 'QUAL_CHEF'],
            'Pharmacien Chef Antenne' => ['direction' => 'ANTENNES', 'niveau' => 'ANT_PHARMA'],
            'Pharmacien Chef d\'Antenne Haut-Ogooué' => ['direction' => 'ANTENNES', 'niveau' => 'ANT_PHARMA'],
            
            // Direction Achats
            'Responsable Achats & Marchés Fournisseurs' => ['direction' => 'ACHATS', 'niveau' => 'ACH_DIRECTEUR'],
            
            // Direction Antennes
            'Chef d\'Antenne Provinciale Ogooué-Maritime' => ['direction' => 'ANTENNES', 'niveau' => 'ANT_CHEF'],
            
            // Direction Urgences
            'Coordonnatrice Nationale des Urgences Sanitaires' => ['direction' => 'URGENCES', 'niveau' => 'URG_COORDINATEUR'],
            
            // Direction Communication
            'Responsable Communication & Relations Extérieures' => ['direction' => 'COMMUNICATION', 'niveau' => 'COM_DIRECTEUR'],
            
            // Direction Portail (Demandeurs)
            'Demandeur - Pharmacien d\'Officine Port-Gentil' => ['direction' => 'PORTAIL', 'niveau' => 'POR_SUPPORT'],
            'Demandeur - Direction CHR Port-Gentil' => ['direction' => 'PORTAIL', 'niveau' => 'POR_SUPPORT'],
            
            // SuperAdmin
            'Super Administrateur National' => ['direction' => 'SUPERADMIN', 'niveau' => 'SA_SUPERADMIN'],
            'Administrateur IT & Cyber-Sécurité' => ['direction' => 'SUPERADMIN', 'niveau' => 'SA_ADMIN'],
            'Auditeur Interne & Conformité Réglementaire' => ['direction' => 'SUPERADMIN', 'niveau' => 'SA_SECURITE'],
        ];

        // Récupérer les directions et niveaux
        $directions = Direction::all()->keyBy('code');
        $niveaux = NiveauAcces::all()->keyBy('code');

        $updatedCount = 0;
        $notFoundCount = 0;

        // Mettre à jour les utilisateurs
        $users = User::all();
        
        foreach ($users as $user) {
            $role = $user->role;
            
            if (!isset($roleMapping[$role])) {
                $notFoundCount++;
                $this->command->warn("Rôle non mappé: {$role} pour utilisateur {$user->email}");
                continue;
            }

            $mapping = $roleMapping[$role];
            $direction = $directions->get($mapping['direction']);
            $niveau = $niveaux->get($mapping['niveau']);

            if (!$direction || !$niveau) {
                $notFoundCount++;
                $this->command->warn("Direction ou niveau non trouvé pour rôle: {$role}");
                continue;
            }

            $user->direction_id = $direction->id;
            $user->niveau_acces_id = $niveau->id;
            $user->save();
            $updatedCount++;
        }

        $this->command->info("Utilisateurs mis à jour: {$updatedCount}");
        $this->command->info("Utilisateurs non mappés: {$notFoundCount}");
    }
}
