<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AntenneProvinciale;
use App\Models\Direction;
use App\Models\Fournisseur;
use App\Models\ProduitPharmaceutique;
use App\Models\StructureSanitaire;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
            'champs' => $def['champs'],
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

        return $data;
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
            'antennes' => [
                'label' => 'Antennes',
                'model' => AntenneProvinciale::class,
                'search' => ['code_antenne', 'nom', 'chef_lieu', 'ville_antenne', 'responsable_nom'],
                'filters' => [
                    ['key' => 'statut_stock', 'column' => 'statut_stock', 'label' => 'Statut stock'],
                    ['key' => 'chef_lieu', 'column' => 'chef_lieu', 'label' => 'Chef-lieu'],
                    ['key' => 'is_active', 'column' => 'is_active', 'label' => 'Active', 'options' => ['1', '0']],
                    ['key' => 'mode_transport_principal', 'column' => 'mode_transport_principal', 'label' => 'Transport'],
                ],
                'colonnes' => [
                    ['key' => 'code_antenne', 'label' => 'Code'],
                    ['key' => 'nom', 'label' => 'Province'],
                    ['key' => 'chef_lieu', 'label' => 'Chef-lieu'],
                    ['key' => 'responsable_nom', 'label' => 'Responsable'],
                    ['key' => 'statut_stock', 'label' => 'Stock'],
                    ['key' => 'is_active', 'label' => 'Active'],
                ],
                'champs' => $this->champs([
                    ['code_antenne', 'Code', 'text', true],
                    ['nom', 'Province', 'text', true],
                    ['chef_lieu', 'Chef-lieu', 'text', true],
                    ['ville_antenne', 'Ville', 'text', true],
                    ['responsable_nom', 'Responsable', 'text', true],
                    ['contact_urgence', 'Contact', 'text', true],
                    ['mode_transport_principal', 'Transport', 'text', true],
                    ['lead_time_jours', 'Délai (jours)', 'number', false],
                    ['statut_stock', 'Statut stock', 'text', false],
                    ['couverture_jours', 'Couverture (jours)', 'number', false],
                    ['is_active', 'Active (1/0)', 'number', false],
                ]),
                'rules' => [
                    'code_antenne' => 'required|string|max:20',
                    'nom' => 'required|string|max:150',
                    'chef_lieu' => 'required|string|max:100',
                    'ville_antenne' => 'required|string|max:150',
                    'responsable_nom' => 'required|string|max:150',
                    'contact_urgence' => 'required|string|max:100',
                    'mode_transport_principal' => 'required|string|max:150',
                    'lead_time_jours' => 'nullable|integer|min:0',
                    'statut_stock' => 'nullable|string|max:30',
                    'couverture_jours' => 'nullable|integer|min:0',
                    'is_active' => 'nullable|boolean',
                ],
                'query' => fn () => AntenneProvinciale::query(),
                'present' => fn (AntenneProvinciale $row) => [
                    'id' => $row->id,
                    'code_antenne' => $row->code_antenne,
                    'nom' => $row->nom,
                    'chef_lieu' => $row->chef_lieu,
                    'ville_antenne' => $row->ville_antenne,
                    'responsable_nom' => $row->responsable_nom,
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
            'structures' => [
                'label' => 'Structures et pharmacies',
                'model' => StructureSanitaire::class,
                'search' => ['code_structure', 'nom', 'ville', 'responsable_nom', 'province_code'],
                'filters' => [
                    ['key' => 'type_structure', 'column' => 'type_structure', 'label' => 'Type'],
                    ['key' => 'antenne_id', 'column' => 'antenne_id', 'label' => 'Antenne (id)'],
                    ['key' => 'province_code', 'column' => 'province_code', 'label' => 'Province'],
                    ['key' => 'statut', 'column' => 'statut', 'label' => 'Statut'],
                    ['key' => 'ville', 'column' => 'ville', 'label' => 'Ville'],
                ],
                'colonnes' => [
                    ['key' => 'code_structure', 'label' => 'Code'],
                    ['key' => 'nom', 'label' => 'Nom'],
                    ['key' => 'type_structure', 'label' => 'Type'],
                    ['key' => 'antenne', 'label' => 'Antenne'],
                    ['key' => 'ville', 'label' => 'Ville'],
                    ['key' => 'statut', 'label' => 'Statut'],
                ],
                'champs' => $this->champs([
                    ['code_structure', 'Code', 'text', true],
                    ['nom', 'Nom', 'text', true],
                    ['type_structure', 'Type', 'text', true],
                    ['antenne_id', 'Antenne (id)', 'number', true],
                    ['province_code', 'Code province', 'text', true],
                    ['departement', 'Département', 'text', true],
                    ['ville', 'Ville', 'text', true],
                    ['responsable_nom', 'Responsable', 'text', false],
                    ['contact_urgence', 'Contact', 'text', true],
                    ['email', 'Courriel', 'text', false],
                    ['statut', 'Statut', 'text', false],
                ]),
                'rules' => [
                    'code_structure' => 'required|string|max:50',
                    'nom' => 'required|string|max:200',
                    'type_structure' => 'required|string|max:80',
                    'antenne_id' => 'required|integer|exists:antennes_provinciales,id',
                    'province_code' => 'required|string|max:20',
                    'departement' => 'required|string|max:100',
                    'ville' => 'required|string|max:100',
                    'responsable_nom' => 'nullable|string|max:150',
                    'contact_urgence' => 'required|string|max:100',
                    'email' => 'nullable|email|max:150',
                    'statut' => 'nullable|string|max:40',
                ],
                'query' => fn () => StructureSanitaire::query()->with('antenne:id,nom'),
                'present' => fn (StructureSanitaire $row) => [
                    'id' => $row->id,
                    'code_structure' => $row->code_structure,
                    'nom' => $row->nom,
                    'type_structure' => $row->type_structure,
                    'antenne_id' => $row->antenne_id,
                    'antenne' => $row->antenne?->nom,
                    'province_code' => $row->province_code,
                    'departement' => $row->departement,
                    'ville' => $row->ville,
                    'responsable_nom' => $row->responsable_nom,
                    'contact_urgence' => $row->contact_urgence,
                    'email' => $row->email,
                    'statut' => $row->statut,
                ],
            ],
            'fournisseurs' => [
                'label' => 'Fournisseurs',
                'model' => Fournisseur::class,
                'search' => ['code_fournisseur', 'nom', 'pays', 'contact_nom', 'contact_email'],
                'filters' => [
                    ['key' => 'type_fournisseur', 'column' => 'type_fournisseur', 'label' => 'Type'],
                    ['key' => 'pays', 'column' => 'pays', 'label' => 'Pays'],
                    ['key' => 'statut_agrement', 'column' => 'statut_agrement', 'label' => 'Agrément'],
                ],
                'colonnes' => [
                    ['key' => 'code_fournisseur', 'label' => 'Code'],
                    ['key' => 'nom', 'label' => 'Nom'],
                    ['key' => 'pays', 'label' => 'Pays'],
                    ['key' => 'type_fournisseur', 'label' => 'Type'],
                    ['key' => 'statut_agrement', 'label' => 'Agrément'],
                ],
                'champs' => $this->champs([
                    ['code_fournisseur', 'Code', 'text', true],
                    ['nom', 'Nom', 'text', true],
                    ['pays', 'Pays', 'text', true],
                    ['type_fournisseur', 'Type', 'text', true],
                    ['statut_agrement', 'Agrément', 'text', false],
                    ['contact_nom', 'Contact', 'text', false],
                    ['contact_email', 'Courriel', 'text', false],
                    ['contact_telephone', 'Téléphone', 'text', false],
                    ['delai_moyen_livraison_jours', 'Délai (jours)', 'number', false],
                ]),
                'rules' => [
                    'code_fournisseur' => 'required|string|max:50',
                    'nom' => 'required|string|max:200',
                    'pays' => 'required|string|max:80',
                    'type_fournisseur' => 'required|string|max:80',
                    'statut_agrement' => 'nullable|string|max:40',
                    'contact_nom' => 'nullable|string|max:150',
                    'contact_email' => 'nullable|email|max:150',
                    'contact_telephone' => 'nullable|string|max:50',
                    'delai_moyen_livraison_jours' => 'nullable|integer|min:0',
                ],
                'query' => fn () => Fournisseur::query(),
                'present' => fn (Fournisseur $row) => $row->only(['id', 'code_fournisseur', 'nom', 'pays', 'type_fournisseur', 'statut_agrement', 'contact_nom', 'contact_email', 'contact_telephone', 'delai_moyen_livraison_jours']),
            ],
        ];
    }

    private function champs(array $defs): array
    {
        return array_map(fn ($item) => [
            'key' => $item[0],
            'label' => $item[1],
            'type' => $item[2],
            'required' => $item[3],
        ], $defs);
    }
}
