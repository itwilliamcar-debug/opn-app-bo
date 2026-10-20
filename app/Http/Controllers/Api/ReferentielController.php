<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AntenneProvinciale;
use App\Models\CategorieMedicament;
use App\Models\Departement;
use App\Models\Direction;
use App\Models\FormeGalenique;
use App\Models\Fournisseur;
use App\Models\Pays;
use App\Models\ProduitPharmaceutique;
use App\Models\Province;
use App\Models\StructureSanitaire;
use App\Models\TypeFournisseur;
use App\Models\TypeStructure;
use App\Models\UniteGestion;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReferentielController extends Controller
{
    public function ressources(): JsonResponse
    {
        $liste = [];
        foreach ($this->catalog() as $key => $def) {
            $liste[] = ['key' => $key, 'label' => $def['label']];
        }

        return response()->json(['success' => true, 'ressources' => $liste]);
    }

    public function index(Request $request, string $ressource): JsonResponse
    {
        $def = $this->definition($ressource);
        $query = $def['query']();

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function (Builder $inner) use ($def, $q) {
                foreach ($def['search'] as $column) {
                    $inner->orWhere($column, 'like', '%'.$q.'%');
                }
            });
        }

        foreach ($def['filters'] as $filter) {
            $value = $request->query($filter['key']);
            if ($value === null || $value === '' || $value === 'ALL') {
                continue;
            }
            $query->where($filter['column'], $value);
        }

        $perPage = min(50, max(3, (int) $request->query('per_page', 10)));
        $page = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'ressource' => $ressource,
            'label' => $def['label'],
            'colonnes' => $def['colonnes'],
            'filtres' => $this->filterOptions($def),
            'champs' => $this->avecOptions($def['champs']),
            'rows' => collect($page->items())->map(fn ($row) => $def['present']($row))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'per_page' => $page->perPage(),
            ],
        ]);
    }

    public function store(Request $request, string $ressource): JsonResponse
    {
        $def = $this->definition($ressource);
        $data = $request->validate($def['rules']);
        $data = $this->prepare($ressource, $data, true);
        try {
            $model = $def['model']::create($data);
            $this->assurerChefLieu($ressource, $model);
        } catch (QueryException $e) {
            return response()->json(['success' => false, 'message' => 'Ce code ou cet email existe déjà.'], 422);
        }

        return response()->json(['success' => true, 'row' => $def['present']($model->fresh())], 201);
    }

    /**
     * Import Excel/CSV des médicaments (lignes déjà parsées côté front).
     * Colonnes attendues : code_produit, designation, dci, categorie, forme, dosage,
     * conditionnement, unite_gestion, pays_provenance, laboratoire, prix_unitaire_xaf,
     * prix_public_plafonne_xaf, stock_national, seuil_min_national, seuil_critique_national, statut.
     */
    public function importMedicaments(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'lignes' => 'required|array|min:1|max:2000',
            'lignes.*.code_produit' => 'required|string|max:50',
            'lignes.*.designation' => 'required|string|max:255',
            'lignes.*.dci' => 'required|string|max:255',
            'lignes.*.categorie' => 'required|string|max:150',
            'lignes.*.forme' => 'required|string|max:150',
            'lignes.*.dosage' => 'required|string|max:150',
            'lignes.*.conditionnement' => 'required|string|max:150',
            'lignes.*.unite_gestion' => 'required|string|max:80',
            'lignes.*.pays_provenance' => 'nullable|string|max:150',
            'lignes.*.laboratoire' => 'nullable|string|max:150',
            'lignes.*.prix_unitaire_xaf' => 'required|numeric|min:0',
            'lignes.*.prix_public_plafonne_xaf' => 'required|numeric|min:0',
            'lignes.*.stock_national' => 'required|integer|min:0',
            'lignes.*.seuil_min_national' => 'nullable|integer|min:0',
            'lignes.*.seuil_critique_national' => 'nullable|integer|min:0',
            'lignes.*.statut' => 'nullable|string|max:40',
        ]);

        $crees = 0;
        $misAJour = 0;
        $erreurs = [];

        DB::transaction(function () use ($payload, &$crees, &$misAJour, &$erreurs) {
            foreach ($payload['lignes'] as $index => $ligne) {
                $numero = $index + 2; // ligne Excel (en-tête = 1)
                try {
                    $categorie = $this->trouverOuCreerLibelle(CategorieMedicament::class, (string) $ligne['categorie']);
                    $forme = $this->trouverOuCreerLibelle(FormeGalenique::class, (string) $ligne['forme']);
                    $unite = $this->trouverOuCreerLibelle(UniteGestion::class, (string) $ligne['unite_gestion']);
                    $paysId = $this->trouverPaysId($ligne['pays_provenance'] ?? null);

                    $data = [
                        'designation' => $ligne['designation'],
                        'dci' => $ligne['dci'],
                        'categorie_id' => $categorie->id,
                        'categorie' => $categorie->libelle,
                        'forme_id' => $forme->id,
                        'forme' => $forme->libelle,
                        'dosage' => $ligne['dosage'],
                        'conditionnement' => $ligne['conditionnement'],
                        'unite_gestion_id' => $unite->id,
                        'unite_gestion' => $unite->libelle,
                        'pays_provenance_id' => $paysId,
                        'laboratoire' => $ligne['laboratoire'] ?? null,
                        'prix_unitaire_xaf' => $ligne['prix_unitaire_xaf'],
                        'prix_public_plafonne_xaf' => $ligne['prix_public_plafonne_xaf'],
                        'stock_national' => (int) $ligne['stock_national'],
                        'seuil_min_national' => isset($ligne['seuil_min_national']) ? (int) $ligne['seuil_min_national'] : 1000,
                        'seuil_critique_national' => isset($ligne['seuil_critique_national']) ? (int) $ligne['seuil_critique_national'] : 500,
                        'statut' => strtoupper((string) ($ligne['statut'] ?: 'DISPONIBLE')),
                    ];

                    $existant = ProduitPharmaceutique::query()
                        ->where('code_produit', $ligne['code_produit'])
                        ->first();

                    if ($existant) {
                        $existant->update($data);
                        $misAJour++;
                    } else {
                        ProduitPharmaceutique::create([
                            'code_produit' => $ligne['code_produit'],
                            ...$data,
                        ]);
                        $crees++;
                    }
                } catch (\Throwable $e) {
                    $erreurs[] = [
                        'ligne' => $numero,
                        'code' => $ligne['code_produit'] ?? '',
                        'message' => $e->getMessage(),
                    ];
                }
            }
        });

        return response()->json([
            'success' => true,
            'crees' => $crees,
            'mis_a_jour' => $misAJour,
            'erreurs' => $erreurs,
            'message' => sprintf(
                'Import terminé : %d créé(s), %d mis à jour%s.',
                $crees,
                $misAJour,
                count($erreurs) ? ', '.count($erreurs).' erreur(s)' : ''
            ),
        ]);
    }

    private function trouverOuCreerLibelle(string $modelClass, string $libelle)
    {
        $libelle = trim($libelle);
        $existant = $modelClass::query()
            ->whereRaw('LOWER(libelle) = ?', [mb_strtolower($libelle)])
            ->first();
        if ($existant) {
            return $existant;
        }
        $code = Str::upper(Str::limit(Str::slug($libelle, '_'), 48, ''));
        if ($code === '') {
            $code = 'REF_'.substr(md5($libelle), 0, 8);
        }
        $base = $code;
        $i = 1;
        while ($modelClass::query()->where('code', $code)->exists()) {
            $code = Str::limit($base.'_'.$i, 50, '');
            $i++;
        }

        return $modelClass::create(['code' => $code, 'libelle' => $libelle]);
    }

    private function trouverPaysId(?string $valeur): ?int
    {
        $valeur = trim((string) $valeur);
        if ($valeur === '') {
            return null;
        }
        $needle = mb_strtolower($valeur);
        $pays = Pays::query()
            ->where(function ($query) use ($needle) {
                $query->whereRaw('LOWER(nom) = ?', [$needle])
                    ->orWhereRaw('LOWER(code) = ?', [$needle]);
            })
            ->first();

        return $pays?->id;
    }

    public function update(Request $request, string $ressource, int $id): JsonResponse
    {
        $def = $this->definition($ressource);
        $model = $def['model']::findOrFail($id);
        $rules = [];
        foreach ($def['rules'] as $key => $rule) {
            $rules[$key] = 'sometimes|'.$rule;
        }
        $data = $request->validate($rules);
        $data = $this->prepare($ressource, $data, false);
        try {
            $model->update($data);
            $this->assurerChefLieu($ressource, $model->fresh());
        } catch (QueryException $e) {
            return response()->json(['success' => false, 'message' => 'Ce code ou cet email existe déjà.'], 422);
        }

        return response()->json(['success' => true, 'row' => $def['present']($model->fresh())]);
    }

    public function destroy(Request $request, string $ressource, int $id): JsonResponse
    {
        $def = $this->definition($ressource);
        $model = $def['model']::findOrFail($id);
        if ($ressource === 'utilisateurs' && (int) $model->id === (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 422);
        }
        try {
            $model->delete();
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Cet élément est encore utilisé et ne peut pas être supprimé.'], 422);
        }

        return response()->json(['success' => true]);
    }

    private function definition(string $ressource): array
    {
        $catalog = $this->catalog();
        if (!isset($catalog[$ressource])) {
            abort(404, 'Référentiel inconnu.');
        }

        return $catalog[$ressource];
    }

    private function filterOptions(array $def): array
    {
        $out = [];
        foreach ($def['filters'] as $filter) {
            if (($filter['column'] ?? '') === 'province_id' && !isset($filter['options'])) {
                $out[] = [
                    'key' => $filter['key'],
                    'label' => $filter['label'],
                    'options' => Province::query()->orderBy('nom')->get(['id', 'nom'])
                        ->map(fn (Province $province) => ['value' => (string) $province->id, 'label' => $province->nom])
                        ->all(),
                ];
                continue;
            }
            if (($filter['column'] ?? '') === 'pays_id' && !isset($filter['options'])) {
                $out[] = [
                    'key' => $filter['key'],
                    'label' => $filter['label'],
                    'options' => Pays::query()->orderBy('nom')->get(['id', 'nom'])
                        ->map(fn (Pays $pays) => ['value' => (string) $pays->id, 'label' => $pays->nom])
                        ->all(),
                ];
                continue;
            }
            if (($filter['column'] ?? '') === 'antenne_id' && !isset($filter['options'])) {
                $out[] = [
                    'key' => $filter['key'],
                    'label' => $filter['label'],
                    'options' => AntenneProvinciale::query()->orderBy('nom')->get(['id', 'code_antenne', 'nom'])
                        ->map(fn (AntenneProvinciale $antenne) => ['value' => (string) $antenne->id, 'label' => $antenne->code_antenne.' — '.$antenne->nom])
                        ->all(),
                ];
                continue;
            }
            if (($filter['column'] ?? '') === 'type_structure_id' && !isset($filter['options'])) {
                $out[] = [
                    'key' => $filter['key'],
                    'label' => $filter['label'],
                    'options' => TypeStructure::query()->orderBy('libelle')->get(['id', 'libelle'])
                        ->map(fn (TypeStructure $type) => ['value' => (string) $type->id, 'label' => $type->libelle])
                        ->all(),
                ];
                continue;
            }
            if (($filter['column'] ?? '') === 'type_fournisseur_id' && !isset($filter['options'])) {
                $out[] = [
                    'key' => $filter['key'],
                    'label' => $filter['label'],
                    'options' => TypeFournisseur::query()->orderBy('libelle')->get(['id', 'libelle'])
                        ->map(fn (TypeFournisseur $type) => ['value' => (string) $type->id, 'label' => $type->libelle])
                        ->all(),
                ];
                continue;
            }
            if (($filter['column'] ?? '') === 'categorie_id' && !isset($filter['options'])) {
                $out[] = [
                    'key' => $filter['key'],
                    'label' => $filter['label'],
                    'options' => CategorieMedicament::query()->orderBy('libelle')->get(['id', 'libelle'])
                        ->map(fn (CategorieMedicament $row) => ['value' => (string) $row->id, 'label' => $row->libelle])
                        ->all(),
                ];
                continue;
            }
            if (($filter['column'] ?? '') === 'forme_id' && !isset($filter['options'])) {
                $out[] = [
                    'key' => $filter['key'],
                    'label' => $filter['label'],
                    'options' => FormeGalenique::query()->orderBy('libelle')->get(['id', 'libelle'])
                        ->map(fn (FormeGalenique $row) => ['value' => (string) $row->id, 'label' => $row->libelle])
                        ->all(),
                ];
                continue;
            }
            if (($filter['column'] ?? '') === 'pays_provenance_id' && !isset($filter['options'])) {
                $out[] = [
                    'key' => $filter['key'],
                    'label' => $filter['label'],
                    'options' => Pays::query()->orderBy('nom')->get(['id', 'nom'])
                        ->map(fn (Pays $pays) => ['value' => (string) $pays->id, 'label' => $pays->nom])
                        ->all(),
                ];
                continue;
            }
            $options = $filter['options'] ?? $def['model']::query()
                ->whereNotNull($filter['column'])
                ->distinct()
                ->orderBy($filter['column'])
                ->pluck($filter['column'])
                ->map(fn ($value) => (string) $value)
                ->values()
                ->all();
            $out[] = [
                'key' => $filter['key'],
                'label' => $filter['label'],
                'options' => $options,
            ];
        }

        return $out;
    }

    private function prepare(string $ressource, array $data, bool $creating): array
    {
        if ($ressource === 'utilisateurs') {
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } elseif ($creating) {
                $data['password'] = Hash::make('OPN2026@Libreville');
            } else {
                unset($data['password']);
            }
        }

        if ($ressource === 'antennes') {
            foreach (['lead_time_jours', 'couverture_jours', 'statut_stock', 'is_active'] as $cle) {
                if (array_key_exists($cle, $data) && $data[$cle] === '') {
                    unset($data[$cle]);
                }
            }
            $province = Province::find($data['province_id'] ?? 0);
            $ville = Ville::find($data['ville_id'] ?? 0);
            $responsable = User::find($data['responsable_user_id'] ?? 0);
            if ($province && $ville && (int) $ville->province_id !== (int) $province->id) {
                throw ValidationException::withMessages([
                    'ville_id' => 'Cette ville n’appartient pas à la province choisie.',
                ]);
            }
            if ($province) {
                $data['nom'] = $province->nom;
                $data['chef_lieu'] = $province->chef_lieu;
            }
            if ($ville) {
                $data['ville_antenne'] = $ville->nom;
            }
            if ($responsable) {
                $data['responsable_nom'] = $responsable->name;
            }
        }

        if ($ressource === 'fournisseurs') {
            foreach (['statut_agrement', 'contact_nom', 'contact_email', 'contact_telephone', 'delai_moyen_livraison_jours'] as $cle) {
                if (array_key_exists($cle, $data) && $data[$cle] === '') {
                    unset($data[$cle]);
                }
            }
            $pays = Pays::find($data['pays_id'] ?? 0);
            $type = TypeFournisseur::find($data['type_fournisseur_id'] ?? 0);
            if ($pays) {
                $data['pays'] = $pays->nom;
            }
            if ($type) {
                $data['type_fournisseur'] = $type->code;
            }
        }

        if ($ressource === 'structures') {
            foreach (['email', 'statut', 'responsable_user_id'] as $cle) {
                if (array_key_exists($cle, $data) && $data[$cle] === '') {
                    unset($data[$cle]);
                }
            }
            $type = TypeStructure::find($data['type_structure_id'] ?? 0);
            $province = Province::find($data['province_id'] ?? 0);
            $antenne = AntenneProvinciale::find($data['antenne_id'] ?? 0);
            $departement = Departement::find($data['departement_id'] ?? 0);
            $ville = Ville::find($data['ville_id'] ?? 0);
            $responsable = User::find($data['responsable_user_id'] ?? 0);
            if ($province && $antenne && (int) $antenne->province_id !== (int) $province->id) {
                throw ValidationException::withMessages(['antenne_id' => 'Cette antenne n’appartient pas à la province choisie.']);
            }
            if ($province && $departement && (int) $departement->province_id !== (int) $province->id) {
                throw ValidationException::withMessages(['departement_id' => 'Ce département n’appartient pas à la province choisie.']);
            }
            if ($province && $ville && (int) $ville->province_id !== (int) $province->id) {
                throw ValidationException::withMessages(['ville_id' => 'Cette ville n’appartient pas à la province choisie.']);
            }
            if ($type) {
                $data['type_structure'] = $type->code;
            }
            if ($province) {
                $data['province_code'] = $province->code;
            }
            if ($departement) {
                $data['departement'] = $departement->nom;
            }
            if ($ville) {
                $data['ville'] = $ville->nom;
            }
            if ($responsable) {
                $data['responsable_nom'] = $responsable->name;
            }
        }

        if ($ressource === 'medicaments') {
            foreach (['pays_provenance_id', 'laboratoire', 'seuil_min_national', 'seuil_critique_national'] as $cle) {
                if (array_key_exists($cle, $data) && $data[$cle] === '') {
                    unset($data[$cle]);
                }
            }
            if (array_key_exists('is_public_catalogue', $data)) {
                $data['is_public_catalogue'] = in_array((string) $data['is_public_catalogue'], ['1', 'true', 'oui'], true);
            }
            $categorie = CategorieMedicament::find($data['categorie_id'] ?? 0);
            $forme = FormeGalenique::find($data['forme_id'] ?? 0);
            $unite = UniteGestion::find($data['unite_gestion_id'] ?? 0);
            if ($categorie) {
                $data['categorie'] = $categorie->libelle;
            }
            if ($forme) {
                $data['forme'] = $forme->libelle;
            }
            if ($unite) {
                $data['unite_gestion'] = $unite->libelle;
            }
        }

        return $data;
    }

    private function assurerChefLieu(string $ressource, $model): void
    {
        if ($ressource !== 'provinces' || !$model?->chef_lieu) {
            return;
        }
        Ville::firstOrCreate([
            'province_id' => $model->id,
            'nom' => $model->chef_lieu,
        ]);
    }

    private function catalog(): array
    {
        return [
            'medicaments' => [
                'label' => 'Médicaments',
                'model' => ProduitPharmaceutique::class,
                'search' => ['code_produit', 'designation', 'dci', 'laboratoire', 'categorie', 'forme', 'conditionnement'],
                'filters' => [
                    ['key' => 'categorie_id', 'column' => 'categorie_id', 'label' => 'Catégorie'],
                    ['key' => 'forme_id', 'column' => 'forme_id', 'label' => 'Forme'],
                    ['key' => 'pays_provenance_id', 'column' => 'pays_provenance_id', 'label' => 'Pays de provenance'],
                    ['key' => 'statut', 'column' => 'statut', 'label' => 'Statut', 'options' => [
                        ['value' => 'DISPONIBLE', 'label' => 'Disponible'],
                        ['value' => 'STOCK_FAIBLE', 'label' => 'Stock faible'],
                        ['value' => 'RUPTURE', 'label' => 'Rupture'],
                        ['value' => 'INACTIF', 'label' => 'Inactif'],
                    ]],
                ],
                'colonnes' => [
                    ['key' => 'code_produit', 'label' => 'Code'],
                    ['key' => 'designation', 'label' => 'Désignation'],
                    ['key' => 'categorie', 'label' => 'Catégorie'],
                    ['key' => 'forme', 'label' => 'Forme'],
                    ['key' => 'pays_provenance', 'label' => 'Pays de provenance'],
                    ['key' => 'stock_national', 'label' => 'Quantité'],
                    ['key' => 'prix_unitaire_xaf', 'label' => 'Prix U. XAF'],
                    ['key' => 'statut', 'label' => 'Statut'],
                ],
                'champs' => $this->champs([
                    ['code_produit', 'Code', 'text', true],
                    ['designation', 'Désignation', 'text', true],
                    ['dci', 'DCI', 'text', true],
                    ['categorie_id', 'Catégorie', 'select', true, 'categories_medicaments'],
                    ['forme_id', 'Forme galénique', 'select', true, 'formes_galeniques'],
                    ['dosage', 'Dosage', 'text', true],
                    ['conditionnement', 'Conditionnement', 'text', true],
                    ['unite_gestion_id', 'Unité de gestion', 'select', true, 'unites_gestion'],
                    ['pays_provenance_id', 'Pays de provenance', 'select', false, 'pays'],
                    ['laboratoire', 'Laboratoire', 'text', false],
                    ['prix_unitaire_xaf', 'Prix unitaire (XAF)', 'number', true],
                    ['prix_public_plafonne_xaf', 'Prix public plafonné (XAF)', 'number', true],
                    ['stock_national', 'Quantité (stock national)', 'number', true],
                    ['seuil_min_national', 'Seuil minimum', 'number', false],
                    ['seuil_critique_national', 'Seuil critique', 'number', false],
                    ['statut', 'Statut', 'select', true, 'statuts_medicament'],
                    ['is_public_catalogue', 'Catalogue public', 'select', false, 'oui_non'],
                ]),
                'rules' => [
                    'code_produit' => 'required|string|max:50',
                    'designation' => 'required|string|max:255',
                    'dci' => 'required|string|max:255',
                    'categorie_id' => 'required|integer|exists:categories_medicaments,id',
                    'forme_id' => 'required|integer|exists:formes_galeniques,id',
                    'dosage' => 'required|string|max:150',
                    'conditionnement' => 'required|string|max:150',
                    'unite_gestion_id' => 'required|integer|exists:unites_gestion,id',
                    'pays_provenance_id' => 'nullable|integer|exists:pays,id',
                    'laboratoire' => 'nullable|string|max:150',
                    'prix_unitaire_xaf' => 'required|numeric|min:0',
                    'prix_public_plafonne_xaf' => 'required|numeric|min:0',
                    'stock_national' => 'required|integer|min:0',
                    'seuil_min_national' => 'nullable|integer|min:0',
                    'seuil_critique_national' => 'nullable|integer|min:0',
                    'statut' => 'required|string|max:40',
                    'is_public_catalogue' => 'nullable|boolean',
                ],
                'query' => fn () => ProduitPharmaceutique::query()->with([
                    'categorieRef:id,libelle',
                    'formeRef:id,libelle',
                    'uniteRef:id,libelle',
                    'paysProvenance:id,nom',
                ]),
                'present' => fn (ProduitPharmaceutique $row) => [
                    'id' => $row->id,
                    'code_produit' => $row->code_produit,
                    'designation' => $row->designation,
                    'dci' => $row->dci,
                    'categorie_id' => $row->categorie_id ? (string) $row->categorie_id : '',
                    'forme_id' => $row->forme_id ? (string) $row->forme_id : '',
                    'unite_gestion_id' => $row->unite_gestion_id ? (string) $row->unite_gestion_id : '',
                    'pays_provenance_id' => $row->pays_provenance_id ? (string) $row->pays_provenance_id : '',
                    'categorie' => $row->categorieRef?->libelle ?: $row->categorie,
                    'forme' => $row->formeRef?->libelle ?: $row->forme,
                    'unite_gestion' => $row->uniteRef?->libelle ?: $row->unite_gestion,
                    'pays_provenance' => $row->paysProvenance?->nom ?: '—',
                    'dosage' => $row->dosage,
                    'conditionnement' => $row->conditionnement,
                    'laboratoire' => $row->laboratoire,
                    'prix_unitaire_xaf' => $row->prix_unitaire_xaf,
                    'prix_public_plafonne_xaf' => $row->prix_public_plafonne_xaf,
                    'stock_national' => $row->stock_national,
                    'seuil_min_national' => $row->seuil_min_national,
                    'seuil_critique_national' => $row->seuil_critique_national,
                    'statut' => $row->statut,
                    'is_public_catalogue' => $row->is_public_catalogue ? '1' : '0',
                ],
            ],
            'categories_medicaments' => [
                'label' => 'Catégories médicaments',
                'model' => CategorieMedicament::class,
                'search' => ['code', 'libelle'],
                'filters' => [],
                'colonnes' => [
                    ['key' => 'code', 'label' => 'Code'],
                    ['key' => 'libelle', 'label' => 'Libellé'],
                ],
                'champs' => $this->champs([
                    ['code', 'Code', 'text', true],
                    ['libelle', 'Libellé', 'text', true],
                ]),
                'rules' => [
                    'code' => 'required|string|max:50',
                    'libelle' => 'required|string|max:150',
                ],
                'query' => fn () => CategorieMedicament::query(),
                'present' => fn (CategorieMedicament $row) => $row->only(['id', 'code', 'libelle']),
            ],
            'formes_galeniques' => [
                'label' => 'Formes galéniques',
                'model' => FormeGalenique::class,
                'search' => ['code', 'libelle'],
                'filters' => [],
                'colonnes' => [
                    ['key' => 'code', 'label' => 'Code'],
                    ['key' => 'libelle', 'label' => 'Libellé'],
                ],
                'champs' => $this->champs([
                    ['code', 'Code', 'text', true],
                    ['libelle', 'Libellé', 'text', true],
                ]),
                'rules' => [
                    'code' => 'required|string|max:50',
                    'libelle' => 'required|string|max:150',
                ],
                'query' => fn () => FormeGalenique::query(),
                'present' => fn (FormeGalenique $row) => $row->only(['id', 'code', 'libelle']),
            ],
            'unites_gestion' => [
                'label' => 'Unités de gestion',
                'model' => UniteGestion::class,
                'search' => ['code', 'libelle'],
                'filters' => [],
                'colonnes' => [
                    ['key' => 'code', 'label' => 'Code'],
                    ['key' => 'libelle', 'label' => 'Libellé'],
                ],
                'champs' => $this->champs([
                    ['code', 'Code', 'text', true],
                    ['libelle', 'Libellé', 'text', true],
                ]),
                'rules' => [
                    'code' => 'required|string|max:50',
                    'libelle' => 'required|string|max:80',
                ],
                'query' => fn () => UniteGestion::query(),
                'present' => fn (UniteGestion $row) => $row->only(['id', 'code', 'libelle']),
            ],
            'directions' => [
                'label' => 'Directions',
                'model' => Direction::class,
                'search' => ['code', 'nom', 'description'],
                'filters' => [
                    ['key' => 'actif', 'column' => 'actif', 'label' => 'Active', 'options' => ['1', '0']],
                ],
                'colonnes' => [
                    ['key' => 'code', 'label' => 'Code'],
                    ['key' => 'nom', 'label' => 'Nom'],
                    ['key' => 'description', 'label' => 'Description'],
                    ['key' => 'ordre', 'label' => 'Ordre'],
                    ['key' => 'actif', 'label' => 'Active'],
                ],
                'champs' => $this->champs([
                    ['code', 'Code', 'text', true],
                    ['nom', 'Nom', 'text', true],
                    ['description', 'Description', 'text', false],
                    ['couleur', 'Couleur', 'text', false],
                    ['ordre', 'Ordre', 'number', false],
                    ['actif', 'Active (1/0)', 'number', false],
                ]),
                'rules' => [
                    'code' => 'required|string|max:50',
                    'nom' => 'required|string|max:150',
                    'description' => 'nullable|string|max:1000',
                    'couleur' => 'nullable|string|max:30',
                    'ordre' => 'nullable|integer',
                    'actif' => 'nullable|boolean',
                ],
                'query' => fn () => Direction::query(),
                'present' => fn (Direction $row) => [
                    'id' => $row->id,
                    'code' => $row->code,
                    'nom' => $row->nom,
                    'description' => $row->description,
                    'couleur' => $row->couleur,
                    'ordre' => $row->ordre,
                    'actif' => $row->actif ? '1' : '0',
                ],
            ],
            'provinces' => [
                'label' => 'Provinces',
                'model' => Province::class,
                'search' => ['code', 'nom', 'chef_lieu'],
                'filters' => [
                    ['key' => 'chef_lieu', 'column' => 'chef_lieu', 'label' => 'Chef-lieu'],
                ],
                'colonnes' => [
                    ['key' => 'code', 'label' => 'Code'],
                    ['key' => 'nom', 'label' => 'Province'],
                    ['key' => 'chef_lieu', 'label' => 'Chef-lieu'],
                ],
                'champs' => $this->champs([
                    ['code', 'Code', 'text', true],
                    ['nom', 'Nom de la province', 'text', true],
                    ['chef_lieu', 'Chef-lieu', 'text', true],
                ]),
                'rules' => [
                    'code' => 'required|string|max:20',
                    'nom' => 'required|string|max:150',
                    'chef_lieu' => 'required|string|max:150',
                ],
                'query' => fn () => Province::query(),
                'present' => fn (Province $row) => [
                    'id' => $row->id,
                    'code' => $row->code,
                    'nom' => $row->nom,
                    'chef_lieu' => $row->chef_lieu,
                ],
            ],
            'villes' => [
                'label' => 'Villes',
                'model' => Ville::class,
                'search' => ['nom'],
                'filters' => [
                    ['key' => 'province_id', 'column' => 'province_id', 'label' => 'Province'],
                ],
                'colonnes' => [
                    ['key' => 'nom', 'label' => 'Ville'],
                    ['key' => 'province', 'label' => 'Province'],
                ],
                'champs' => $this->champs([
                    ['province_id', 'Province', 'select', true, 'provinces'],
                    ['nom', 'Nom de la ville', 'text', true],
                ]),
                'rules' => [
                    'province_id' => 'required|integer|exists:provinces,id',
                    'nom' => 'required|string|max:150',
                ],
                'query' => fn () => Ville::query()->with('province:id,nom'),
                'present' => fn (Ville $row) => [
                    'id' => $row->id,
                    'nom' => $row->nom,
                    'province_id' => (string) $row->province_id,
                    'province' => $row->province?->nom,
                ],
            ],
            'antennes' => [
                'label' => 'Antennes',
                'model' => AntenneProvinciale::class,
                'search' => ['code_antenne', 'nom', 'chef_lieu', 'ville_antenne', 'responsable_nom'],
                'filters' => [
                    ['key' => 'statut_stock', 'column' => 'statut_stock', 'label' => 'Statut stock'],
                    ['key' => 'province_id', 'column' => 'province_id', 'label' => 'Province'],
                    ['key' => 'is_active', 'column' => 'is_active', 'label' => 'Active', 'options' => ['1', '0']],
                    ['key' => 'mode_transport_principal', 'column' => 'mode_transport_principal', 'label' => 'Transport'],
                ],
                'colonnes' => [
                    ['key' => 'code_antenne', 'label' => 'Code'],
                    ['key' => 'province', 'label' => 'Province'],
                    ['key' => 'ville', 'label' => 'Ville'],
                    ['key' => 'responsable', 'label' => 'Responsable'],
                    ['key' => 'statut_stock', 'label' => 'Stock'],
                    ['key' => 'is_active', 'label' => 'Active'],
                ],
                'champs' => $this->champs([
                    ['code_antenne', 'Code', 'text', true],
                    ['province_id', 'Province', 'select', true, 'provinces'],
                    ['ville_id', 'Ville', 'select', true, 'villes', 'province_id'],
                    ['responsable_user_id', 'Responsable', 'select', true, 'responsables'],
                    ['contact_urgence', 'Contact', 'text', true],
                    ['mode_transport_principal', 'Transport', 'text', true],
                    ['lead_time_jours', 'Délai (jours)', 'number', false],
                    ['statut_stock', 'Statut stock', 'select', false, 'statuts_stock'],
                    ['couverture_jours', 'Couverture (jours)', 'number', false],
                    ['is_active', 'Active', 'select', false, 'oui_non'],
                ]),
                'rules' => [
                    'code_antenne' => 'required|string|max:20',
                    'province_id' => 'required|integer|exists:provinces,id',
                    'ville_id' => 'required|integer|exists:villes,id',
                    'responsable_user_id' => 'required|integer|exists:users,id',
                    'contact_urgence' => 'required|string|max:100',
                    'mode_transport_principal' => 'required|string|max:150',
                    'lead_time_jours' => 'nullable|integer|min:0',
                    'statut_stock' => 'nullable|string|max:30',
                    'couverture_jours' => 'nullable|integer|min:0',
                    'is_active' => 'nullable|boolean',
                ],
                'query' => fn () => AntenneProvinciale::query()->with(['province:id,nom,chef_lieu', 'ville:id,nom', 'responsable:id,name']),
                'present' => fn (AntenneProvinciale $row) => [
                    'id' => $row->id,
                    'code_antenne' => $row->code_antenne,
                    'province_id' => $row->province_id ? (string) $row->province_id : '',
                    'ville_id' => $row->ville_id ? (string) $row->ville_id : '',
                    'responsable_user_id' => $row->responsable_user_id ? (string) $row->responsable_user_id : '',
                    'province' => $row->province?->nom ?: $row->nom,
                    'ville' => $row->ville?->nom ?: $row->ville_antenne,
                    'responsable' => $row->responsable?->name ?: $row->responsable_nom,
                    'contact_urgence' => $row->contact_urgence,
                    'mode_transport_principal' => $row->mode_transport_principal,
                    'lead_time_jours' => $row->lead_time_jours,
                    'statut_stock' => $row->statut_stock,
                    'couverture_jours' => $row->couverture_jours,
                    'is_active' => $row->is_active ? '1' : '0',
                ],
            ],
            'utilisateurs' => [
                'label' => 'Agents et utilisateurs',
                'model' => User::class,
                'search' => ['name', 'email', 'role', 'code_role', 'entite', 'telephone'],
                'filters' => [
                    ['key' => 'code_role', 'column' => 'code_role', 'label' => 'Profil'],
                    ['key' => 'role', 'column' => 'role', 'label' => 'Fonction'],
                    ['key' => 'province_id', 'column' => 'province_id', 'label' => 'Antenne (id)'],
                    ['key' => 'entite', 'column' => 'entite', 'label' => 'Entité'],
                ],
                'colonnes' => [
                    ['key' => 'name', 'label' => 'Nom'],
                    ['key' => 'email', 'label' => 'Email'],
                    ['key' => 'role', 'label' => 'Fonction'],
                    ['key' => 'code_role', 'label' => 'Profil'],
                    ['key' => 'antenne', 'label' => 'Antenne'],
                    ['key' => 'entite', 'label' => 'Entité'],
                ],
                'champs' => $this->champs([
                    ['name', 'Nom', 'text', true],
                    ['email', 'Email', 'text', true],
                    ['password', 'Mot de passe', 'text', false],
                    ['role', 'Fonction', 'text', true],
                    ['code_role', 'Profil', 'text', true],
                    ['entite', 'Entité', 'text', false],
                    ['province_id', 'Antenne (id)', 'number', false],
                    ['telephone', 'Téléphone', 'text', false],
                ]),
                'rules' => [
                    'name' => 'required|string|max:150',
                    'email' => 'required|email|max:150',
                    'password' => 'nullable|string|min:6|max:100',
                    'role' => 'required|string|max:100',
                    'code_role' => 'required|string|max:50',
                    'entite' => 'nullable|string|max:150',
                    'province_id' => 'nullable|integer|exists:antennes_provinciales,id',
                    'telephone' => 'nullable|string|max:50',
                ],
                'query' => fn () => User::query()->with('province:id,nom,chef_lieu'),
                'present' => fn (User $row) => [
                    'id' => $row->id,
                    'name' => $row->name,
                    'email' => $row->email,
                    'role' => $row->role,
                    'code_role' => $row->code_role,
                    'entite' => $row->entite,
                    'province_id' => $row->province_id,
                    'telephone' => $row->telephone,
                    'antenne' => $row->province?->nom,
                ],
            ],
            'types_structures' => [
                'label' => 'Types de structures',
                'model' => TypeStructure::class,
                'search' => ['code', 'libelle'],
                'filters' => [],
                'colonnes' => [
                    ['key' => 'code', 'label' => 'Code'],
                    ['key' => 'libelle', 'label' => 'Type'],
                ],
                'champs' => $this->champs([
                    ['code', 'Code', 'text', true],
                    ['libelle', 'Libellé', 'text', true],
                ]),
                'rules' => [
                    'code' => 'required|string|max:80',
                    'libelle' => 'required|string|max:150',
                ],
                'query' => fn () => TypeStructure::query(),
                'present' => fn (TypeStructure $row) => [
                    'id' => $row->id,
                    'code' => $row->code,
                    'libelle' => $row->libelle,
                ],
            ],
            'departements' => [
                'label' => 'Départements',
                'model' => Departement::class,
                'search' => ['code', 'nom'],
                'filters' => [
                    ['key' => 'province_id', 'column' => 'province_id', 'label' => 'Province'],
                ],
                'colonnes' => [
                    ['key' => 'code', 'label' => 'Code'],
                    ['key' => 'nom', 'label' => 'Département'],
                    ['key' => 'province', 'label' => 'Province'],
                ],
                'champs' => $this->champs([
                    ['code', 'Code', 'text', true],
                    ['nom', 'Nom du département', 'text', true],
                    ['province_id', 'Province', 'select', true, 'provinces'],
                ]),
                'rules' => [
                    'code' => 'required|string|max:40',
                    'nom' => 'required|string|max:150',
                    'province_id' => 'required|integer|exists:provinces,id',
                ],
                'query' => fn () => Departement::query()->with('province:id,nom'),
                'present' => fn (Departement $row) => [
                    'id' => $row->id,
                    'code' => $row->code,
                    'nom' => $row->nom,
                    'province_id' => (string) $row->province_id,
                    'province' => $row->province?->nom,
                ],
            ],
            'structures' => [
                'label' => 'Structures et pharmacies',
                'model' => StructureSanitaire::class,
                'search' => ['code_structure', 'nom', 'ville', 'responsable_nom', 'province_code'],
                'filters' => [
                    ['key' => 'type_structure_id', 'column' => 'type_structure_id', 'label' => 'Type'],
                    ['key' => 'antenne_id', 'column' => 'antenne_id', 'label' => 'Antenne'],
                    ['key' => 'province_id', 'column' => 'province_id', 'label' => 'Province'],
                    ['key' => 'statut', 'column' => 'statut', 'label' => 'Statut'],
                ],
                'colonnes' => [
                    ['key' => 'code_structure', 'label' => 'Code'],
                    ['key' => 'nom', 'label' => 'Nom'],
                    ['key' => 'type', 'label' => 'Type'],
                    ['key' => 'antenne', 'label' => 'Antenne'],
                    ['key' => 'ville', 'label' => 'Ville'],
                    ['key' => 'statut', 'label' => 'Statut'],
                ],
                'champs' => $this->champs([
                    ['code_structure', 'Code', 'text', true],
                    ['nom', 'Nom', 'text', true],
                    ['type_structure_id', 'Type', 'select', true, 'types_structures'],
                    ['province_id', 'Province', 'select', true, 'provinces'],
                    ['antenne_id', 'Antenne', 'select', true, 'antennes', 'province_id'],
                    ['departement_id', 'Département', 'select', true, 'departements', 'province_id'],
                    ['ville_id', 'Ville', 'select', true, 'villes', 'province_id'],
                    ['responsable_user_id', 'Responsable', 'select', false, 'responsables'],
                    ['contact_urgence', 'Contact', 'text', true],
                    ['email', 'Email', 'text', false],
                    ['statut', 'Statut', 'text', false],
                ]),
                'rules' => [
                    'code_structure' => 'required|string|max:50',
                    'nom' => 'required|string|max:200',
                    'type_structure_id' => 'required|integer|exists:types_structures,id',
                    'province_id' => 'required|integer|exists:provinces,id',
                    'antenne_id' => 'required|integer|exists:antennes_provinciales,id',
                    'departement_id' => 'required|integer|exists:departements,id',
                    'ville_id' => 'required|integer|exists:villes,id',
                    'responsable_user_id' => 'nullable|integer|exists:users,id',
                    'contact_urgence' => 'required|string|max:100',
                    'email' => 'nullable|email|max:150',
                    'statut' => 'nullable|string|max:40',
                ],
                'query' => fn () => StructureSanitaire::query()->with([
                    'type:id,libelle',
                    'antenne:id,nom,code_antenne',
                    'province:id,nom,code',
                    'departementAdmin:id,nom',
                    'villeRattachee:id,nom',
                    'responsable:id,name',
                ]),
                'present' => fn (StructureSanitaire $row) => [
                    'id' => $row->id,
                    'code_structure' => $row->code_structure,
                    'nom' => $row->nom,
                    'type_structure_id' => $row->type_structure_id ? (string) $row->type_structure_id : '',
                    'province_id' => $row->province_id ? (string) $row->province_id : '',
                    'antenne_id' => $row->antenne_id ? (string) $row->antenne_id : '',
                    'departement_id' => $row->departement_id ? (string) $row->departement_id : '',
                    'ville_id' => $row->ville_id ? (string) $row->ville_id : '',
                    'responsable_user_id' => $row->responsable_user_id ? (string) $row->responsable_user_id : '',
                    'type' => $row->type?->libelle ?: $row->type_structure,
                    'antenne' => $row->antenne?->nom,
                    'province' => $row->province?->nom,
                    'departement' => $row->departementAdmin?->nom ?: $row->departement,
                    'ville' => $row->villeRattachee?->nom ?: $row->ville,
                    'responsable' => $row->responsable?->name ?: $row->responsable_nom,
                    'contact_urgence' => $row->contact_urgence,
                    'email' => $row->email,
                    'statut' => $row->statut,
                ],
            ],
            'pays' => [
                'label' => 'Pays de provenance',
                'model' => Pays::class,
                'search' => ['code', 'nom'],
                'filters' => [],
                'colonnes' => [
                    ['key' => 'code', 'label' => 'Code'],
                    ['key' => 'nom', 'label' => 'Pays de provenance'],
                ],
                'champs' => $this->champs([
                    ['code', 'Code', 'text', true],
                    ['nom', 'Nom du pays de provenance', 'text', true],
                ]),
                'rules' => [
                    'code' => 'required|string|max:40',
                    'nom' => 'required|string|max:150',
                ],
                'query' => fn () => Pays::query(),
                'present' => fn (Pays $row) => [
                    'id' => $row->id,
                    'code' => $row->code,
                    'nom' => $row->nom,
                ],
            ],
            'types_fournisseurs' => [
                'label' => 'Types de fournisseurs',
                'model' => TypeFournisseur::class,
                'search' => ['code', 'libelle'],
                'filters' => [],
                'colonnes' => [
                    ['key' => 'code', 'label' => 'Code'],
                    ['key' => 'libelle', 'label' => 'Type'],
                ],
                'champs' => $this->champs([
                    ['code', 'Code', 'text', true],
                    ['libelle', 'Libellé', 'text', true],
                ]),
                'rules' => [
                    'code' => 'required|string|max:80',
                    'libelle' => 'required|string|max:150',
                ],
                'query' => fn () => TypeFournisseur::query(),
                'present' => fn (TypeFournisseur $row) => [
                    'id' => $row->id,
                    'code' => $row->code,
                    'libelle' => $row->libelle,
                ],
            ],
            'fournisseurs' => [
                'label' => 'Fournisseurs',
                'model' => Fournisseur::class,
                'search' => ['code_fournisseur', 'nom', 'pays', 'contact_nom', 'contact_email'],
                'filters' => [
                    ['key' => 'type_fournisseur_id', 'column' => 'type_fournisseur_id', 'label' => 'Type'],
                    ['key' => 'pays_id', 'column' => 'pays_id', 'label' => 'Pays'],
                    ['key' => 'statut_agrement', 'column' => 'statut_agrement', 'label' => 'Agrément'],
                ],
                'colonnes' => [
                    ['key' => 'code_fournisseur', 'label' => 'Code'],
                    ['key' => 'nom', 'label' => 'Nom'],
                    ['key' => 'pays', 'label' => 'Pays'],
                    ['key' => 'type', 'label' => 'Type'],
                    ['key' => 'statut_agrement', 'label' => 'Agrément'],
                ],
                'champs' => $this->champs([
                    ['code_fournisseur', 'Code', 'text', true],
                    ['nom', 'Nom', 'text', true],
                    ['pays_id', 'Pays', 'select', true, 'pays'],
                    ['type_fournisseur_id', 'Type', 'select', true, 'types_fournisseurs'],
                    ['statut_agrement', 'Agrément', 'text', false],
                    ['contact_nom', 'Contact', 'text', false],
                    ['contact_email', 'Email', 'text', false],
                    ['contact_telephone', 'Téléphone', 'text', false],
                    ['delai_moyen_livraison_jours', 'Délai (jours)', 'number', false],
                ]),
                'rules' => [
                    'code_fournisseur' => 'required|string|max:50',
                    'nom' => 'required|string|max:200',
                    'pays_id' => 'required|integer|exists:pays,id',
                    'type_fournisseur_id' => 'required|integer|exists:types_fournisseurs,id',
                    'statut_agrement' => 'nullable|string|max:40',
                    'contact_nom' => 'nullable|string|max:150',
                    'contact_email' => 'nullable|email|max:150',
                    'contact_telephone' => 'nullable|string|max:50',
                    'delai_moyen_livraison_jours' => 'nullable|integer|min:0',
                ],
                'query' => fn () => Fournisseur::query()->with(['paysOrigine:id,nom', 'type:id,code,libelle']),
                'present' => fn (Fournisseur $row) => [
                    'id' => $row->id,
                    'code_fournisseur' => $row->code_fournisseur,
                    'nom' => $row->nom,
                    'pays_id' => $row->pays_id ? (string) $row->pays_id : '',
                    'type_fournisseur_id' => $row->type_fournisseur_id ? (string) $row->type_fournisseur_id : '',
                    'pays' => $row->paysOrigine?->nom ?: $row->pays,
                    'type' => $row->type?->libelle ?: $row->type_fournisseur,
                    'statut_agrement' => $row->statut_agrement,
                    'contact_nom' => $row->contact_nom,
                    'contact_email' => $row->contact_email,
                    'contact_telephone' => $row->contact_telephone,
                    'delai_moyen_livraison_jours' => $row->delai_moyen_livraison_jours,
                ],
            ],
        ];
    }

    private function champs(array $defs): array
    {
        return array_map(function ($item) {
            $champ = [
                'key' => $item[0],
                'label' => $item[1],
                'type' => $item[2],
                'required' => $item[3],
            ];
            if (isset($item[4])) {
                $champ['source'] = $item[4];
            }
            if (isset($item[5])) {
                $champ['dependsOn'] = $item[5];
            }

            return $champ;
        }, $defs);
    }

    private function avecOptions(array $champs): array
    {
        $sources = [
            'provinces' => Province::query()->orderBy('nom')->get(['id', 'nom'])
                ->map(fn (Province $province) => ['value' => (string) $province->id, 'label' => $province->nom])
                ->all(),
            'villes' => Ville::query()->orderBy('nom')->get(['id', 'nom', 'province_id'])
                ->map(fn (Ville $ville) => ['value' => (string) $ville->id, 'label' => $ville->nom, 'parent' => (string) $ville->province_id])
                ->all(),
            'responsables' => User::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $user) => ['value' => (string) $user->id, 'label' => $user->name])
                ->all(),
            'pays' => Pays::query()->orderBy('nom')->get(['id', 'nom'])
                ->map(fn (Pays $pays) => ['value' => (string) $pays->id, 'label' => $pays->nom])
                ->all(),
            'types_fournisseurs' => TypeFournisseur::query()->orderBy('libelle')->get(['id', 'libelle'])
                ->map(fn (TypeFournisseur $type) => ['value' => (string) $type->id, 'label' => $type->libelle])
                ->all(),
            'types_structures' => TypeStructure::query()->orderBy('libelle')->get(['id', 'libelle'])
                ->map(fn (TypeStructure $type) => ['value' => (string) $type->id, 'label' => $type->libelle])
                ->all(),
            'antennes' => AntenneProvinciale::query()->orderBy('nom')->get(['id', 'code_antenne', 'nom', 'province_id'])
                ->map(fn (AntenneProvinciale $antenne) => [
                    'value' => (string) $antenne->id,
                    'label' => $antenne->code_antenne.' — '.$antenne->nom,
                    'parent' => (string) $antenne->province_id,
                ])
                ->all(),
            'departements' => Departement::query()->orderBy('nom')->get(['id', 'nom', 'province_id'])
                ->map(fn (Departement $departement) => [
                    'value' => (string) $departement->id,
                    'label' => $departement->nom,
                    'parent' => (string) $departement->province_id,
                ])
                ->all(),
            'statuts_stock' => [
                ['value' => 'NORMAL', 'label' => 'Normal'],
                ['value' => 'VIGILANCE', 'label' => 'Vigilance'],
                ['value' => 'CRITIQUE', 'label' => 'Critique'],
            ],
            'statuts_medicament' => [
                ['value' => 'DISPONIBLE', 'label' => 'Disponible'],
                ['value' => 'STOCK_FAIBLE', 'label' => 'Stock faible'],
                ['value' => 'RUPTURE', 'label' => 'Rupture'],
                ['value' => 'INACTIF', 'label' => 'Inactif'],
            ],
            'categories_medicaments' => CategorieMedicament::query()->orderBy('libelle')->get(['id', 'libelle'])
                ->map(fn (CategorieMedicament $row) => ['value' => (string) $row->id, 'label' => $row->libelle])
                ->all(),
            'formes_galeniques' => FormeGalenique::query()->orderBy('libelle')->get(['id', 'libelle'])
                ->map(fn (FormeGalenique $row) => ['value' => (string) $row->id, 'label' => $row->libelle])
                ->all(),
            'unites_gestion' => UniteGestion::query()->orderBy('libelle')->get(['id', 'libelle'])
                ->map(fn (UniteGestion $row) => ['value' => (string) $row->id, 'label' => $row->libelle])
                ->all(),
            'oui_non' => [
                ['value' => '1', 'label' => 'Oui'],
                ['value' => '0', 'label' => 'Non'],
            ],
        ];

        return array_map(function (array $champ) use ($sources) {
            if (!empty($champ['source'])) {
                $champ['options'] = $sources[$champ['source']] ?? [];
            }

            return $champ;
        }, $champs);
    }
}
