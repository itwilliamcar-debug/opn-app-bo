<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'auth_mode')) {
                $table->string('auth_mode', 20)->default('simple')->after('password');
            }
            if (!Schema::hasColumn('users', 'totp_secret')) {
                $table->string('totp_secret', 64)->nullable()->after('auth_mode');
            }
            if (!Schema::hasColumn('users', 'totp_confirmed_at')) {
                $table->timestamp('totp_confirmed_at')->nullable()->after('totp_secret');
            }
        });

        // Aligner les comptes déjà en 2FA legacy sur OTP e-mail.
        if (Schema::hasColumn('users', 'is_2fa_enabled')) {
            DB::table('users')->where('is_2fa_enabled', true)->where('auth_mode', 'simple')->update([
                'auth_mode' => 'otp_email',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'totp_confirmed_at')) {
                $table->dropColumn('totp_confirmed_at');
            }
            if (Schema::hasColumn('users', 'totp_secret')) {
                $table->dropColumn('totp_secret');
            }
            if (Schema::hasColumn('users', 'auth_mode')) {
                $table->dropColumn('auth_mode');
            }
        });
    }
};
