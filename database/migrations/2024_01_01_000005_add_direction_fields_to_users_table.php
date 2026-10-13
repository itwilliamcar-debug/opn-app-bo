<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('direction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('niveau_acces_id')->nullable()->constrained('niveau_acces')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['direction_id']);
            $table->dropForeign(['niveau_acces_id']);
            $table->dropColumn(['direction_id', 'niveau_acces_id']);
        });
    }
};
