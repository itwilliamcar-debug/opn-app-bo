<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niveau_acces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direction_id')->constrained()->onDelete('cascade');
            $table->string('code')->unique();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->integer('niveau_hierarchie')->default(1);
            $table->json('permissions')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niveau_acces');
    }
};
