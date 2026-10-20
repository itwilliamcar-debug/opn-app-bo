<?php

namespace App\Services;

use App\Mail\AuthOtpMail;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;

class AuthModeService
{
    public const MODE_SIMPLE = 'simple';

    public const MODE_OTP_EMAIL = 'otp_email';

    public const MODE_TOTP = 'totp';

    public const MODES = [self::MODE_SIMPLE, self::MODE_OTP_EMAIL, self::MODE_TOTP];

    public function modeEffectif(User $user): string
    {
        $mode = $user->auth_mode ?: self::MODE_SIMPLE;
        if (!in_array($mode, self::MODES, true)) {
            return $user->is_2fa_enabled ? self::MODE_OTP_EMAIL : self::MODE_SIMPLE;
        }

        return $mode;
    }

    public function appliquerMode(User $user, string $mode): void
    {
        if (!in_array($mode, self::MODES, true)) {
            $mode = self::MODE_SIMPLE;
        }

        $payload = [
            'auth_mode' => $mode,
            'is_2fa_enabled' => $mode !== self::MODE_SIMPLE,
        ];

        if ($mode === self::MODE_SIMPLE) {
            $payload['totp_secret'] = null;
            $payload['totp_confirmed_at'] = null;
            $payload['two_factor_code'] = null;
            $payload['two_factor_expires_at'] = null;
        } elseif ($mode === self::MODE_OTP_EMAIL) {
            $payload['totp_secret'] = null;
            $payload['totp_confirmed_at'] = null;
        } elseif ($mode === self::MODE_TOTP) {
            // Nouvelle activation : forcer un scan QR à la prochaine connexion.
            $google2fa = new Google2FA;
            $payload['totp_secret'] = $google2fa->generateSecretKey(32);
            $payload['totp_confirmed_at'] = null;
            $payload['two_factor_code'] = null;
            $payload['two_factor_expires_at'] = null;
        }

        $user->forceFill($payload)->save();
    }

    public function genererEtEnvoyerOtpEmail(User $user): string
    {
        $otpCode = (string) random_int(100000, 999999);
        $user->forceFill([
            'two_factor_code' => $otpCode,
            'two_factor_expires_at' => now()->addMinutes(10),
        ])->save();

        try {
            Mail::to($user->email)->send(new AuthOtpMail($user, $otpCode));
        } catch (\Throwable) {
            // En local / mail log : le code reste disponible via dev_otp_hint.
        }

        return $otpCode;
    }

    public function otpEmailValide(User $user, string $code): bool
    {
        if (!$user->two_factor_code || !$user->two_factor_expires_at || now()->gt($user->two_factor_expires_at)) {
            return false;
        }
        $stored = (string) $user->two_factor_code;
        if (str_starts_with($stored, 'plain:')) {
            return hash_equals(substr($stored, 6), $code);
        }
        if (preg_match('/^\d{6}$/', $stored)) {
            return hash_equals($stored, $code);
        }

        return \Illuminate\Support\Facades\Hash::check($code, $stored);
    }

    public function totpValide(User $user, string $code): bool
    {
        if (!$user->totp_secret) {
            return false;
        }
        $google2fa = new Google2FA;

        return $google2fa->verifyKey($user->totp_secret, $code, 1);
    }

    public function provisioningUri(User $user): string
    {
        $google2fa = new Google2FA;
        $secret = $user->totp_secret ?: $google2fa->generateSecretKey(32);
        if (!$user->totp_secret) {
            $user->forceFill(['totp_secret' => $secret])->save();
        }

        return $google2fa->getQRCodeUrl('OPN Gabon', $user->email, $secret);
    }

    public function qrSvg(User $user): string
    {
        $uri = $this->provisioningUri($user);
        $renderer = new ImageRenderer(new RendererStyle(220), new SvgImageBackEnd);
        $writer = new Writer($renderer);

        return $writer->writeString($uri);
    }

    public function confirmerTotp(User $user, string $code): bool
    {
        if (!$this->totpValide($user, $code)) {
            return false;
        }
        $user->forceFill(['totp_confirmed_at' => now()])->save();

        return true;
    }
}
