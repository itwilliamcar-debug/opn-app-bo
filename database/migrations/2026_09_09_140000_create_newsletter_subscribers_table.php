<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('newsletter_subscribers')) {
            Schema::create('newsletter_subscribers', function (Blueprint $table) {
                $table->id();
                $table->string('email')->unique();
                $table->string('nom')->nullable();
                $table->string('organisation')->nullable();
                $table->string('statut')->default('ACTIF');
                $table->string('ip_address')->nullable();
                $table->string('derniere_newsletter_id')->nullable();
                $table->timestamp('derniere_newsletter_date')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
