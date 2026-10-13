<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direction_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direction_id')->constrained()->onDelete('cascade');
            $table->string('code');
            $table->string('nom');
            $table->text('description')->nullable();
            $table->string('icone')->default('LayoutDashboard');
            $table->string('route')->nullable();
            $table->integer('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            
            $table->unique(['direction_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direction_modules');
    }
};
