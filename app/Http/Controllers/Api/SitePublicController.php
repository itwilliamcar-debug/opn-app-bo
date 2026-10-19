<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Actualite;
use App\Models\AntenneProvinciale;
use App\Models\AttributionMarche;
use App\Models\ContenuSite;
use App\Models\Fournisseur;
use App\Models\LotProduit;
use App\Models\MarchePublic;
use App\Models\MissionLogistique;
use App\Models\NewsletterSubscriber;
use App\Models\ProduitPharmaceutique;
use App\Models\StructureSanitaire;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class SitePublicController extends Controller
{
    public function index(): JsonResponse
    {
        $antennes = AntenneProvinciale::query()
            ->where('is_active', true)
            ->orderBy('chef_lieu')
            ->get();

        $antenneMap = [];
        foreach ($antennes as $antenne) {
            $antenneMap[$antenne->id] = $this->mapCode($antenne->code_antenne);
        }

        $stocks = [];
        if (Schema::hasTable('lots_produits')) {
            $rows = LotProduit::query()
                ->selectRaw('produit_id, antenne_id, SUM(quantite_disponible) as qte')
                ->where('is_quarantine', false)
                ->groupBy('produit_id', 'antenne_id')
                ->get();
            foreach ($rows as $row) {
                $code = $antenneMap[$row->antenne_id] ?? null;
                if (! $code) {
                    continue;
                }
                $stocks[$row->produit_id][$code] = (int) $row->qte;
            }
        }

        $produits = ProduitPharmaceutique::query()
            ->where('is_public_catalogue', true)
            ->orderBy('designation')
            ->get()
            ->map(function (ProduitPharmaceutique $p) use ($stocks) {
                $prix = (float) $p->prix_unitaire_xaf;

                return [
                    'id' => $p->id,
                    'code' => $p->code_produit,
                    'designation' => $p->designation,
                    'dci' => $p->dci,
                    'forme' => $p->forme,
                    'dosage' => $p->dosage,
                    'conditionnement' => $p->conditionnement,
                    'famille' => $p->categorie,
                    'categorie' => $p->categorie,
                    'laboratoire' => $p->laboratoire,
                    'uniteGestion' => $p->unite_gestion,
                    'prixUnitaireXAF' => $prix,
                    'prixPublicPlafonneXAF' => (float) $p->prix_public_plafonne_xaf,
                    'stockNational' => (int) $p->stock_national,
                    'statut' => $p->statut ?: 'DISPONIBLE',
                    'gratuit' => $prix <= 0,
                    'stocksParAntenne' => $stocks[$p->id] ?? null,
                ];
            })
            ->values();

        $familles = $produits->pluck('categorie')->filter()->unique()->sort()->values();
        $familles = collect(['TOUTES LES FAMILLES'])->merge($familles)->values();

        $missionsOuvertes = MissionLogistique::query()
            ->with(['antenneDepart', 'antenneDestination'])
            ->whereNotIn('statut', ['CLOTUREE', 'LIVRAISONS_TERMINEES', 'RAPPORT_SOUMIS'])
            ->orderByDesc('date_depart')
            ->limit(3)
            ->get();

        $totalMissions = MissionLogistique::query()->count();
        $missionsFroides = MissionLogistique::query()->where('temperature_conforme', true)->count();
        $chaineFroid = $totalMissions > 0
            ? (int) round(($missionsFroides / $totalMissions) * 100)
            : 100;

        $contenus = ContenuSite::query()
            ->where('publie', true)
            ->orderBy('ordre')
            ->get()
            ->groupBy('groupe')
            ->map(fn ($rows) => $rows->map(fn (ContenuSite $c) => [
                'cle' => $c->cle,
                'titre' => $c->titre,
                'resume' => $c->resume,
                'contenu' => $c->contenu,
                'meta' => $c->meta ?? [],
            ])->values());

        $contactRow = optional($contenus->get('contact'))->first();
        $dgRow = optional($contenus->get('dg'))->first();
        $heroRow = optional($contenus->get('hero'))->first();

        return response()->json([
            'success' => true,
            'stats' => [
                'antennes' => $antennes->count(),
                'produits' => $produits->count(),
                'familles' => max(0, $familles->count() - 1),
                'chaineFroid' => $chaineFroid,
                'structures' => StructureSanitaire::query()->count(),
                'fournisseurs' => Fournisseur::query()->count(),
                'abonnes' => NewsletterSubscriber::query()->where('statut', 'ACTIF')->count(),
                'marchesOuverts' => MarchePublic::query()->where('publie', true)->where('statut', 'EN_COURS')->count(),
            ],
            'hero' => $heroRow,
            'dg' => $dgRow,
            'contact' => is_array($contactRow) ? ($contactRow['meta'] ?? null) : null,
            'missions' => $contenus->get('mission', collect())->values(),
            'apropos' => [
                'intro' => optional($contenus->get('apropos_intro'))->first(),
                'cadre' => $contenus->get('apropos_cadre', collect())->values(),
                'vision' => $contenus->get('apropos_vision', collect())->values(),
                'objectifs' => $contenus->get('apropos_objectif', collect())->values(),
                'activites' => $contenus->get('apropos_activite', collect())->values(),
            ],
            'antennes' => $antennes->map(fn (AntenneProvinciale $a) => [
                'id' => $this->mapCode($a->code_antenne),
                'code' => $a->code_antenne,
                'nom' => $a->nom,
                'chefLieu' => $a->chef_lieu,
                'villeAntenne' => $a->ville_antenne,
                'responsable' => $a->responsable_nom,
                'contact' => $a->contact_urgence,
                'leadTimeJours' => (int) $a->lead_time_jours,
                'modeTransport' => $a->mode_transport_principal,
                'lat' => $a->latitude !== null ? (float) $a->latitude : null,
                'lng' => $a->longitude !== null ? (float) $a->longitude : null,
                'statutStock' => $a->statut_stock,
                'stockValeurXAF' => (float) $a->stock_valeur_xaf,
                'couvertureJours' => (int) $a->couverture_jours,
                'description' => $a->description,
            ])->sortBy('id')->values(),
            'produits' => $produits,
            'familles' => $familles,
            'actualites' => Actualite::query()
                ->where('publie', true)
                ->orderByDesc('mis_en_avant')
                ->orderBy('ordre')
                ->get()
                ->map(fn (Actualite $a) => [
                    'id' => $a->code,
                    'title' => $a->titre,
                    'date' => $a->date_label,
                    'tag' => $a->tag,
                    'tagColor' => $a->tag_color,
                    'summary' => $a->resume,
                    'content' => $a->contenu,
                ])
                ->values(),
            'marches' => MarchePublic::query()
                ->where('publie', true)
                ->orderBy('ordre')
                ->get()
                ->map(fn (MarchePublic $m) => $this->mapMarche($m))
                ->values(),
            'attributions' => AttributionMarche::query()
                ->where('publie', true)
                ->orderByDesc('id')
                ->get()
                ->map(fn (AttributionMarche $a) => [
                    'ref' => $a->reference,
                    'objet' => $a->objet,
                    'attributaire' => $a->attributaire,
                    'montant' => (float) $a->montant,
                    'dateAttribution' => $a->date_attribution_label,
                    'delaiExecution' => $a->delai_execution,
                    'statutDgcpmp' => $a->statut_dgcpmp,
                ])
                ->values(),
            'suivi' => $missionsOuvertes->map(function (MissionLogistique $m) {
                $dest = $m->antenneDestination;
                $depart = $m->antenneDepart;
                $code = $dest ? $this->mapCode($dest->code_antenne) : 'OPN';
                $eta = null;
                if ($m->date_arrivee_estimee) {
                    $eta = abs((int) now()->diffInHours($m->date_arrivee_estimee, false)).' h';
                }

                return [
                    'code' => $code,
                    'titre' => $m->convoi_nom ?: $m->code_mission,
                    'trajet' => trim(($depart?->chef_lieu ?? 'Owendo').' ➔ '.($dest?->chef_lieu ?? 'destination').($eta ? " ({$eta})" : '')),
                    'statut' => $this->libelleStatutMission($m->statut, (bool) $m->temperature_conforme),
                    'tone' => $this->toneMission($m->statut, (bool) $m->temperature_conforme),
                ];
            })->values(),
        ]);
    }

    private function mapMarche(MarchePublic $m): array
    {
        return [
            'id' => $m->code,
            'ref' => $m->reference,
            'titre' => $m->titre,
            'categorie' => $m->categorie,
            'categorieLabel' => $m->categorie_label,
            'type' => $m->type,
            'typeCode' => $m->type_code,
            'datePublication' => $m->date_publication_label,
            'dateLimite' => $m->date_limite_label,
            'budget' => (float) $m->budget,
            'caution' => $m->caution,
            'fraisDao' => $m->frais_dao,
            'statut' => $m->statut,
            'statutLabel' => $m->statut_label,
            'allotissement' => $m->allotissement ?? [],
            'cahierCharges' => $m->cahier_charges,
            'description' => $m->description,
            'conditions' => $m->conditions,
            'autorite' => $m->autorite,
            'accueil' => (bool) $m->afficher_accueil,
        ];
    }

    private function mapCode(?string $code): string
    {
        $code = strtoupper((string) $code);
        if (preg_match('/G(\d)/', $code, $m)) {
            return 'G'.$m[1];
        }
        if ($code === 'EST' || str_contains($code, 'EST')) {
            return 'G1';
        }

        return $code !== '' ? $code : 'G1';
    }

    private function libelleStatutMission(string $statut, bool $froidOk): string
    {
        return match ($statut) {
            'EN_ROUTE' => 'En Transit',
            'CHARGEMENT_CONFIRME' => 'Chargement',
            'ARRIVEE_DESTINATION' => 'À Quai',
            'INCIDENT_DECLARE' => 'Incident',
            default => $froidOk ? 'Contrôle OK' : $statut,
        };
    }

    private function toneMission(string $statut, bool $froidOk): string
    {
        return match ($statut) {
            'EN_ROUTE' => 'transit',
            'ARRIVEE_DESTINATION' => 'quai',
            'INCIDENT_DECLARE' => 'alerte',
            default => $froidOk ? 'ok' : 'transit',
        };
    }
}
