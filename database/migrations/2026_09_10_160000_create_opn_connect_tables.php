<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('connect_conversations')) {
            Schema::create('connect_conversations', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->enum('type', ['direct', 'group', 'channel'])->default('group');
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->string('jitsi_room')->unique();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();

                $table->index('last_message_at');
                $table->index('type');
            });
        }

        if (! Schema::hasTable('connect_conversation_members')) {
            Schema::create('connect_conversation_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')
                    ->constrained('connect_conversations')
                    ->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('role', ['owner', 'admin', 'member'])->default('member');
                $table->timestamp('joined_at')->useCurrent();
                $table->timestamp('last_read_at')->nullable();
                $table->timestamps();

                $table->unique(['conversation_id', 'user_id']);
                $table->index('user_id');
            });
        }

        if (! Schema::hasTable('connect_messages')) {
            Schema::create('connect_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')
                    ->constrained('connect_conversations')
                    ->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('type', ['text', 'audio', 'emoji', 'system'])->default('text');
                $table->text('body')->nullable();
                $table->string('emoji_code')->nullable();
                $table->string('audio_path')->nullable();
                $table->unsignedInteger('audio_duration_sec')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['conversation_id', 'id']);
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('connect_messages');
        Schema::dropIfExists('connect_conversation_members');
        Schema::dropIfExists('connect_conversations');
    }
};
