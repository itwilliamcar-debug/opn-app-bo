<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AntenneProvinciale;
use App\Models\Departement;
use App\Models\Direction;
use App\Models\Fournisseur;
use App\Models\Pays;
use App\Models\ProduitPharmaceutique;
use App\Models\Province;
use App\Models\StructureSanitaire;
use App\Models\TypeFournisseur;
use App\Models\TypeStructure;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            return response()->json(['success' => false, 'message' => 'Ce code ou ce courriel existe déjà.'], 422);
        }

        return response()->json(['success' => true, 'row' => $def['present']($model->fresh())], 201);
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
            return response()->json(['success' => false, 'message' => 'Ce code ou ce courriel existe déjà.'], 422);
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
                'search' => ['code_produit', 'designation', 'dci', 'laboratoire', 'categorie'],
                'filters' => [
                    ['key' => 'categorie', 'column' => 'categorie', 'label' => 'Catégorie'],
                    ['key' => 'forme', 'column' => 'forme', 'label' => 'Forme'],
                    ['key' => 'statut', 'column' => 'statut', 'label' => 'Statut'],
                    ['key' => 'is_public_catalogue', 'column' => 'is_public_catalogue', 'label' => 'Catalogue public', 'options' => ['1', '0']],
                ],
                'colonnes' => [
                    ['key' => 'code_produit', 'label' => 'Code'],
                    ['key' => 'designation', 'label' => 'Désignation'],
                    ['key' => 'dci', 'label' => 'DCI'],
                    ['key' => 'forme', 'label' => 'Forme'],
                    ['key' => 'categorie', 'label' => 'Catégorie'],
                    ['key' => 'statut', 'label' => 'Statut'],
                    ['key' => 'prix_unitaire_xaf', 'label' => 'Prix XAF'],
                ],
                'champs' => $this->champs([
                    ['code_produit', 'Code', 'text', true],
                    ['designation', 'Désignation', 'text', true],
                    ['dci', 'DCI', 'text', true],
                    ['forme', 'Forme', 'text', true],
                    ['dosage', 'Dosage', 'text', true],
                    ['conditionnement', 'Conditionnement', 'text', true],
                    ['categorie', 'Catégorie', 'text', true],
                    ['laboratoire', 'Laboratoire', 'text', false],
                    ['unite_gestion', 'Unité', 'text', false],
                    ['prix_unitaire_xaf', 'Prix unitaire', 'number', true],
                    ['prix_public_plafonne_xaf', 'Prix public', 'number', true],
                    ['statut', 'Statut', 'text', false],
                    ['stock_national', 'Stock national', 'number', false],
                ]),
                'rules' => [
                    'code_produit' => 'required|string|max:50',
                    'designation' => 'required|string|max:255',
                    'dci' => 'required|string|max:255',
                    'forme' => 'required|string|max:150',
                    'dosage' => 'required|string|max:150',
                    'conditionnement' => 'required|string|max:150',
                    'categorie' => 'required|string|max:100',
                    'laboratoire' => 'nullable|string|max:150',
                    'unite_gestion' => 'nullable|string|max:50',
                    'prix_unitaire_xaf' => 'required|numeric|min:0',
                    'prix_public_plafonne_xaf' => 'required|numeric|min:0',
                    'statut' => 'nullable|string|max:40',
                    'stock_national' => 'nullable|integer|min:0',
                ],
                'query' => fn () => ProduitPharmaceutique::query(),
                'present' => fn (ProduitPharmaceutique $row) => $row->only(['id', 'code_produit', 'designation', 'dci', 'forme', 'dosage', 'conditionnement', 'categorie', 'laboratoire', 'unite_gestion', 'prix_unitaire_xaf', 'prix_public_plafonne_xaf', 'statut', 'stock_national']),
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
                    ['key' => 'email', 'label' => 'Courriel'],
                    ['key' => 'role', 'label' => 'Fonction'],
                    ['key' => 'code_role', 'label' => 'Profil'],
                    ['key' => 'antenne', 'label' => 'Antenne'],
                    ['key' => 'entite', 'label' => 'Entité'],
                ],
                'champs' => $this->champs([
                    ['name', 'Nom', 'text', true],
                    ['email', 'Courriel', 'text', true],
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
                    ['email', 'Courriel', 'text', false],
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
                'label' => 'Pays',
                'model' => Pays::class,
                'search' => ['code', 'nom'],
                'filters' => [],
                'colonnes' => [
                    ['key' => 'code', 'label' => 'Code'],
                    ['key' => 'nom', 'label' => 'Pays'],
                ],
                'champs' => $this->champs([
                    ['code', 'Code', 'text', true],
                    ['nom', 'Nom du pays', 'text', true],
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
                    ['contact_email', 'Courriel', 'text', false],
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
