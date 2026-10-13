<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Local Windows : bcrypt coût 12 = ~1s/check et sature artisan serve sous multi-login
        if ($this->app->environment('local')) {
            \Illuminate\Support\Facades\Hash::setRounds(4);
        }

        // SQLite : lectures concurrentes multi-sessions / multi-provinces
        if (config('database.default') === 'sqlite') {
            try {
                DB::statement('PRAGMA journal_mode=WAL;');
                DB::statement('PRAGMA busy_timeout=5000;');
            } catch (\Throwable) {
                // ignore hors contexte DB
            }
        }
    }
}
