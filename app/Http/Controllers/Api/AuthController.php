<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\OpnAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    protected OpnAuthorizationService $authService;

    public function __construct(OpnAuthorizationService $authService)
    {
        $this->authService = $authService;
    }

    protected function formatUserPayload(User $user): array
    {
        $codeRole = $user->code_role ?? 'DEMANDEUR_STRUCTURE';
        $roleConfig = OpnAuthorizationService::ROLES_PERMISSIONS[$codeRole] ?? [
            'scope' => 'PROVINCE',
            'permissions' => ['demand.own.view'],
        ];

        $enforcedIt = $this->authService->getEnforcedItDestination($user);

        return [
            'id' => $user->id,
            'nom' => $user->name,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'code_role' => $user->code_role,
            'codeRole' => $user->code_role,
            'entite' => $user->entite,
            'province_id' => $user->province_id,
            'provinceId' => $user->province_id,
            'structure_id' => $user->structure_id,
            'matricule_opn' => $user->matricule_opn,
            'service' => $user->service,
            'ville' => $user->ville,
            'telephone' => $user->telephone,
            'scope' => $roleConfig['scope'],
            'permissions' => $roleConfig['permissions'],
            'enforced_it_destination' => $enforcedIt,
            'etat' => $user->etat ?: 'actif',
            'poste_comptable' => $user->poste_comptable,
            'profil' => $user->profil ? [
                'id' => $user->profil->id,
                'code' => $user->profil->code,
                'libelle' => $user->profil->libelle,
                'niveau_validation' => $user->profil->niveau_validation,
            ] : null,
            'habilitations' => $this->serialiserHabilitations($user),
        ];
    }

    protected function identiteSession(User $user): array
    {
        $payload = $this->formatUserPayload($user);

        return [
            'user' => $payload,
            'habilitations' => $payload['habilitations'],
        ];
    }

    protected function serialiserHabilitations(User $user): array
    {
        $user->loadMissing('profil.habilitations');
        if (!$user->profil) {
            return [];
        }

        return $user->profil->habilitations->map(function ($habilitation) {
            $creer = (bool) $habilitation->pivot->creer;
            $lire = (bool) $habilitation->pivot->lire;
            $modifier = (bool) $habilitation->pivot->modifier;
            $valider = (bool) $habilitation->pivot->valider;
            return [
                'code' => $habilitation->code,
                'libelle' => $habilitation->libelle,
                'module' => $habilitation->module,
                'creer' => $creer,
                'lire' => $lire,
                'modifier' => $modifier,
                'valider' => $valider,
                'acces' => $creer || $lire || $modifier || $valider,
            ];
        })->values()->all();
    }

    protected function compteInactif(User $user): bool
    {
        return ($user->etat ?? 'actif') === 'inactif';
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $rateKey = 'login:' . strtolower($validated['email']) . '|' . $request->ip();
        $maxAttempts = app()->environment('local') ? 30 : 5;
        if (RateLimiter::tooManyAttempts($rateKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($rateKey);

            return response()->json([
                'success' => false,
                'message' => "Trop de tentatives. Reessayez dans {$seconds} secondes.",
            ], 429);
        }

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($rateKey, 60);

            return response()->json([
                'success' => false,
                'message' => 'Identifiants invalides. Veuillez verifier votre adresse courriel OPN ou mot de passe.',
            ], 401);
        }

        if ($this->compteInactif($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Ce compte est inactif.',
            ], 403);
        }

        RateLimiter::clear($rateKey);

        if ($user->is_2fa_enabled) {
            $otpCode = (string) random_int(100000, 999999);
            // 6 chiffres : la colonne two_factor_code est limitée à 10 caractères.
            $user->forceFill([
                'two_factor_code' => $otpCode,
                'two_factor_expires_at' => now()->addMinutes(10),
            ])->save();

            $payload = [
                'success' => true,
                'requires_2fa' => true,
                'email' => $user->email,
                'message' => 'Code de securite 2FA transmis par SMS / Courriel.',
            ];
            if (app()->environment('local') || config('mail.default') === 'log') {
                $payload['dev_otp_hint'] = $otpCode;
            }

            return response()->json($payload);
        }

        $token = $user->createToken('opn_auth_token')->plainTextToken;

        // Rehash local (coût réduit) après login réussi — accélère les connexions suivantes
        if (app()->environment('local') && Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => Hash::make($validated['password'])])->save();
        }

        AuditLog::create([
            'auteur_nom' => $user->name,
            'role' => $user->role ?? 'Agent OPN',
            'action' => 'CONNEXION_UTILISATEUR',
            'details' => "Connexion reussie a l'intranet OPN Hub ({$user->code_role}).",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            ...$this->identiteSession($user),
            'message' => 'Connexion etablie avec succes.',
        ]);
    }

    public function verify2fa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $rateKey = '2fa:' . strtolower($validated['email']) . '|' . $request->ip();
        $max2fa = app()->environment('local') ? 30 : 5;
        if (RateLimiter::tooManyAttempts($rateKey, $max2fa)) {
            $seconds = RateLimiter::availableIn($rateKey);

            return response()->json([
                'success' => false,
                'message' => "Trop de tentatives 2FA. Reessayez dans {$seconds} secondes.",
            ], 429);
        }

        $user = User::where('email', $validated['email'])->first();

        $otpValid = false;
        if ($user && $user->two_factor_code && $user->two_factor_expires_at && now()->lte($user->two_factor_expires_at)) {
            $stored = (string) $user->two_factor_code;
            if (str_starts_with($stored, 'plain:')) {
                $otpValid = hash_equals(substr($stored, 6), $validated['code']);
            } elseif (preg_match('/^\d{6}$/', $stored)) {
                $otpValid = hash_equals($stored, $validated['code']);
            } else {
                $otpValid = Hash::check($validated['code'], $stored);
            }
        }

        if (!$otpValid) {
            RateLimiter::hit($rateKey, 60);

            return response()->json([
                'success' => false,
                'message' => 'Code 2FA incorrect ou expire.',
            ], 422);
        }

        RateLimiter::clear($rateKey);

        $user->forceFill([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ])->save();

        $token = $user->createToken('opn_auth_token_2fa')->plainTextToken;

        AuditLog::create([
            'auteur_nom' => $user->name,
            'role' => $user->role ?? 'Agent OPN',
            'action' => 'VALIDATION_2FA',
            'details' => "Authentification forte 2FA validee avec succes pour {$user->code_role}.",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            ...$this->identiteSession($user),
            'message' => 'Session 2FA certifiee conforme.',
        ]);
    }

    /**
     * Connexion démo 1 clic — LOCAL uniquement.
     * Pas de bcrypt / pas de 2FA : évite la saturation de php artisan serve.
     */
    public function demoQuick(Request $request): JsonResponse
    {
        if (! app()->environment('local')) {
            return response()->json([
                'success' => false,
                'message' => 'Connexion rapide démo indisponible hors environnement local.',
            ], 403);
        }

        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $validated['email'])->first();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Profil démo introuvable. Vérifiez le seed DatabaseSeeder.',
            ], 404);
        }

        if ($this->compteInactif($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Ce compte est inactif.',
            ], 403);
        }

        // Création token seule (pas de delete préalable) — évite le lock SQLite sous charge
        $token = $user->createToken('opn_demo_quick')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            ...$this->identiteSession($user),
            'message' => 'Session démo ouverte (rapide).',
            'demo_quick' => true,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Non authentifie'], 401);
        }

        return response()->json([
            'success' => true,
            ...$this->identiteSession($user),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Non authentifie'], 401);
        }

        $actuel = $user->currentAccessToken();
        $nouveau = $user->createToken('opn_auth_token');
        $nouvelId = $nouveau->accessToken->id ?? null;
        if ($actuel && $actuel->id !== $nouvelId) {
            $actuel->delete();
        }

        return response()->json([
            'success' => true,
            'token' => $nouveau->plainTextToken,
            ...$this->identiteSession($user),
            'message' => 'Session prolongee.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $user->forceFill(['last_seen_at' => null])->save();
            $user->currentAccessToken()?->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Deconnexion effectuee avec succes.',
        ]);
    }
}
