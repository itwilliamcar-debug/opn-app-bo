<?php

use Database\Seeders\ProfilHabilitationSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('profils') || !Schema::hasTable('habilitations')) {
            return;
        }

        (new ProfilHabilitationSeeder())->run();
    }

    public function down(): void
    {
    }
};
