<?php

namespace Database\Seeders;

use App\Models\ProduitPharmaceutique;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Importe le catalogue national OPN (DCI) depuis database/data/catalogue_national_opn.json
 */
class CatalogNationalSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/catalogue_national_opn.json');
        if (! File::exists($path)) {
            $this->command?->warn('Fichier catalogue_national_opn.json introuvable — seed catalogue ignore.');

            return;
        }

        $rows = json_decode(File::get($path), true);
        if (! is_array($rows) || $rows === []) {
            $this->command?->warn('Catalogue JSON vide.');

            return;
        }

        $n = 0;
        foreach ($rows as $row) {
            if (empty($row['code_produit'])) {
                continue;
            }
            ProduitPharmaceutique::updateOrCreate(
                ['code_produit' => $row['code_produit']],
                [
                    'designation' => $row['designation'] ?? $row['code_produit'],
                    'dci' => $row['dci'] ?? ($row['designation'] ?? 'DCI'),
                    'forme' => $row['forme'] ?? 'Non precise',
                    'dosage' => $row['dosage'] ?? '-',
                    'conditionnement' => $row['conditionnement'] ?? '-',
                    'categorie' => $row['categorie'] ?? 'Divers',
                    'laboratoire' => $row['laboratoire'] ?? null,
                    'unite_gestion' => $row['unite_gestion'] ?? 'Unite',
                    'prix_unitaire_xaf' => $row['prix_unitaire_xaf'] ?? 0,
                    'prix_public_plafonne_xaf' => $row['prix_public_plafonne_xaf'] ?? 0,
                    'stock_national' => $row['stock_national'] ?? 0,
                    'seuil_min_national' => $row['seuil_min_national'] ?? 0,
                    'seuil_critique_national' => $row['seuil_critique_national'] ?? 0,
                    'cmm_nationale' => $row['cmm_nationale'] ?? 0,
                    'delai_moyen_appro_jours' => $row['delai_moyen_appro_jours'] ?? 30,
                    'statut' => $row['statut'] ?? 'DISPONIBLE',
                    'is_public_catalogue' => $row['is_public_catalogue'] ?? true,
                ]
            );
            $n++;
        }

        $this->command?->info("Catalogue national synchronise : {$n} produits.");
    }
}
