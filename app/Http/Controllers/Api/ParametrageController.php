<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AntenneProvinciale;
use App\Models\Direction;
use App\Models\Habilitation;
use App\Models\HabilitationProfil;
use App\Models\Profil;
use App\Models\Province;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ParametrageController extends Controller
{
    private const PROFILS_PLEIN_DROIT = ['sa', 'dsi'];

    public function utilisateurs(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'utilisateurs' => User::with(['profil:id,code,libelle', 'antenne:id,nom,chef_lieu', 'provinceRattachement:id,nom', 'direction:id,nom'])->orderBy('name')->get()->map(fn (User $user) => $this->utilisateur($user)),
        ]);
    }

    /** Provinces, antennes et directions proposées à la création d'un compte. */
    public function territoire(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'provinces' => Province::query()->orderBy('nom')->get(['id', 'nom', 'code']),
            'antennes' => AntenneProvinciale::query()->orderBy('nom')->get(['id', 'code_antenne', 'nom', 'chef_lieu', 'ville_antenne', 'province_id']),
            'directions' => Direction::query()->active()->ordered()->get(['id', 'nom', 'code', 'antenne_id']),
        ]);
    }

    public function storeUtilisateur(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email|unique:users,email',
            'name' => 'required|string|max:150',
            'profil_id' => 'required|exists:profils,id',
            'poste_comptable' => 'nullable|string|max:120',
            'etat' => 'nullable|in:actif,inactif',
            'password' => 'nullable|string|min:6',
            'province_rattachement_id' => 'required|exists:provinces,id',
            'antenne_id' => 'required|exists:antennes_provinciales,id',
            'direction_id' => 'nullable|exists:directions,id',
        ]);
        $profil = Profil::findOrFail($data['profil_id']);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'] ?? 'OPN2026@Libreville',
            'profil_id' => $profil->id,
            'poste_comptable' => $data['poste_comptable'] ?? null,
            'etat' => $data['etat'] ?? 'actif',
            'role' => $profil->libelle,
            'code_role' => $this->codeRole($profil->code),
            ...$this->rattachement($data),
        ]);

        return response()->json(['success' => true, 'utilisateur' => $this->utilisateur($user->load(['profil', 'antenne', 'provinceRattachement', 'direction']))], 201);
    }

    public function updateUtilisateur(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $data = $request->validate([
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'name' => 'sometimes|string|max:150',
            'profil_id' => 'sometimes|exists:profils,id',
            'poste_comptable' => 'nullable|string|max:120',
            'etat' => 'sometimes|in:actif,inactif',
            'password' => 'nullable|string|min:6',
            'province_rattachement_id' => 'sometimes|exists:provinces,id',
            'antenne_id' => 'sometimes|exists:antennes_provinciales,id',
            'direction_id' => 'nullable|exists:directions,id',
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if (!empty($data['profil_id'])) {
            $profil = Profil::findOrFail($data['profil_id']);
            $data['role'] = $profil->libelle;
            $data['code_role'] = $this->codeRole($profil->code);
        }
        if (!empty($data['antenne_id']) || !empty($data['province_rattachement_id'])) {
            $data = array_merge($data, $this->rattachement([
                'province_rattachement_id' => $data['province_rattachement_id'] ?? $user->province_rattachement_id,
                'antenne_id' => $data['antenne_id'] ?? $user->antenne_id,
                'direction_id' => array_key_exists('direction_id', $data) ? $data['direction_id'] : $user->direction_id,
            ]));
        }
        $user->update($data);

        return response()->json(['success' => true, 'utilisateur' => $this->utilisateur($user->fresh(['profil', 'antenne', 'provinceRattachement', 'direction']))]);
    }

    public function desactiverUtilisateur(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['etat' => 'inactif']);

        return response()->json(['success' => true, 'utilisateur' => $this->utilisateur($user->fresh(['profil', 'antenne', 'provinceRattachement', 'direction']))]);
    }

    public function profils(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'profils' => Profil::withCount('utilisateurs')->orderBy('libelle')->get(),
        ]);
    }

    public function storeProfil(Request $request): JsonResponse
    {
        $data = $request->validate([
            'libelle' => 'required|string|max:150',
            'code' => 'nullable|string|max:60',
            'niveau_validation' => 'nullable|integer|min:0|max:9',
            'habilitations' => 'array',
            'habilitations.*.id' => 'required|integer|exists:habilitations,id',
            'habilitations.*.creer' => 'boolean',
            'habilitations.*.lire' => 'boolean',
            'habilitations.*.modifier' => 'boolean',
            'habilitations.*.valider' => 'boolean',
            'habilitations.*.supprimer' => 'boolean',
        ]);
        $code = $this->codeProfil($data['code'] ?? $data['libelle']);
        if (Profil::where('code', $code)->exists()) {
            return response()->json(['success' => false, 'message' => 'Ce code de profil existe déjà.'], 422);
        }
        $profil = Profil::create([
            'code' => $code,
            'libelle' => $data['libelle'],
            'niveau_validation' => $data['niveau_validation'] ?? 0,
            'systeme' => false,
        ]);
        $this->syncHabilitations($profil, $data['habilitations'] ?? [], false);

        return response()->json(['success' => true, 'profil' => $profil->fresh()], 201);
    }

    public function updateProfil(Request $request, int $id): JsonResponse
    {
        $profil = Profil::findOrFail($id);
        $data = $request->validate([
            'libelle' => 'sometimes|string|max:150',
            'niveau_validation' => 'sometimes|integer|min:0|max:9',
        ]);
        $profil->update($data);

        return response()->json(['success' => true, 'profil' => $profil]);
    }

    public function destroyProfil(int $id): JsonResponse
    {
        $profil = Profil::withCount('utilisateurs')->findOrFail($id);
        if ($profil->systeme) {
            return response()->json(['success' => false, 'message' => 'Ce profil livré avec l\'application ne peut pas être supprimé.'], 422);
        }
        if ($profil->utilisateurs_count > 0) {
            return response()->json(['success' => false, 'message' => 'Ce profil est encore attribué à un utilisateur.'], 422);
        }
        $profil->delete();

        return response()->json(['success' => true]);
    }

    public function habilitations(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'habilitations' => Habilitation::orderBy('module')->orderBy('code')->get(),
        ]);
    }

    public function storeHabilitation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:80|unique:habilitations,code',
            'libelle' => 'required|string|max:150',
            'module' => 'required|string|max:40',
        ]);

        return response()->json(['success' => true, 'habilitation' => Habilitation::create($data)], 201);
    }

    public function updateHabilitation(Request $request, int $id): JsonResponse
    {
        $habilitation = Habilitation::findOrFail($id);
        $data = $request->validate([
            'libelle' => 'sometimes|string|max:150',
            'module' => 'sometimes|string|max:40',
        ]);
        $habilitation->update($data);

        return response()->json(['success' => true, 'habilitation' => $habilitation]);
    }

    public function liaisons(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'liaisons' => HabilitationProfil::orderBy('profil_id')->get(),
        ]);
    }

    public function syncProfilHabilitations(Request $request, int $id): JsonResponse
    {
        $profil = Profil::findOrFail($id);
        $data = $request->validate([
            'forceRetrait' => 'boolean',
            'assignations' => 'required|array',
            'assignations.*.idHabilitation' => 'required|integer|exists:habilitations,id',
            'assignations.*.creer' => 'boolean',
            'assignations.*.lire' => 'boolean',
            'assignations.*.modifier' => 'boolean',
            'assignations.*.valider' => 'boolean',
            'assignations.*.supprimer' => 'boolean',
        ]);
        $this->syncHabilitations($profil, array_map(fn ($row) => [
            'id' => $row['idHabilitation'],
            'creer' => $row['creer'] ?? false,
            'lire' => $row['lire'] ?? false,
            'modifier' => $row['modifier'] ?? false,
            'valider' => $row['valider'] ?? false,
            'supprimer' => $row['supprimer'] ?? false,
        ], $data['assignations']), (bool) ($data['forceRetrait'] ?? false));

        return response()->json(['success' => true]);
    }

    private function syncHabilitations(Profil $profil, array $assignations, bool $forceRetrait): void
    {
        $plein = in_array($profil->code, self::PROFILS_PLEIN_DROIT, true) && !$forceRetrait;
        $vus = [];
        foreach ($assignations as $row) {
            $flags = [
                'creer' => $plein || (bool) ($row['creer'] ?? false),
                'lire' => $plein || (bool) ($row['lire'] ?? false),
                'modifier' => $plein || (bool) ($row['modifier'] ?? false),
                'valider' => $plein || (bool) ($row['valider'] ?? false),
                'supprimer' => $plein || (bool) ($row['supprimer'] ?? false),
            ];
            $vus[] = (int) $row['id'];
            if (!in_array(true, $flags, true)) {
                HabilitationProfil::where('profil_id', $profil->id)->where('habilitation_id', $row['id'])->delete();
                continue;
            }
            HabilitationProfil::updateOrCreate(
                ['profil_id' => $profil->id, 'habilitation_id' => $row['id']],
                $flags
            );
        }
        HabilitationProfil::where('profil_id', $profil->id)->whereNotIn('habilitation_id', $vus)->delete();
    }

    private function utilisateur(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'etat' => $user->etat ?: 'actif',
            'poste_comptable' => $user->poste_comptable,
            'profil_id' => $user->profil_id,
            'profil' => $user->profil?->libelle,
            'profil_code' => $user->profil?->code,
            'code_role' => $user->code_role,
            'province_rattachement_id' => $user->province_rattachement_id,
            'province' => $user->provinceRattachement?->nom,
            'antenne_id' => $user->antenne_id,
            'antenne' => $user->antenne?->nom,
            'direction_id' => $user->direction_id,
            'direction' => $user->direction?->nom,
        ];
    }

    /**
     * Vérifie que l'antenne appartient à la province.
     * province_id conserve l'antenne pour le flux déjà en place.
     */
    private function rattachement(array $data): array
    {
        $antenne = AntenneProvinciale::find($data['antenne_id'] ?? 0);
        if (!$antenne || (int) $antenne->province_id !== (int) ($data['province_rattachement_id'] ?? 0)) {
            abort(response()->json(['success' => false, 'message' => 'Cette antenne n\'appartient pas à la province choisie.'], 422));
        }
        if (!empty($data['direction_id'])) {
            $direction = Direction::find($data['direction_id']);
            if ($direction && $direction->antenne_id && (int) $direction->antenne_id !== (int) $antenne->id) {
                abort(response()->json(['success' => false, 'message' => 'Cette direction n\'est pas rattachée à l\'antenne choisie.'], 422));
            }
        }

        return [
            'antenne_id' => $antenne->id,
            'province_id' => $antenne->id,
            'province_rattachement_id' => (int) $data['province_rattachement_id'],
            'direction_id' => $data['direction_id'] ?? null,
        ];
    }

    private function codeProfil(string $libelle): string
    {
        $code = Str::of($libelle)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();

        return $code !== '' ? $code : 'profil';
    }

    private function codeRole(string $profilCode): string
    {
        return match ($profilCode) {
            'sa', 'dsi' => 'SUPER_ADMIN',
            'admin_metier' => 'ADMIN_METIER',
            'dg' => 'DG',
            'daf' => 'DAF',
            'appro_national' => 'APPRO_NATIONAL',
            'demandeur' => 'DEMANDEUR_PHARMACIEN',
            default => 'DEMANDEUR_STRUCTURE',
        };
    }
}
