<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_threads', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | USER
            |--------------------------------------------------------------------------
            */

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | HIERARCHY
            |--------------------------------------------------------------------------
            */

            $table->foreignId('program_id')
                ->nullable()
                ->constrained('programs')
                ->nullOnDelete();

            $table->foreignId('level_id')
                ->nullable()
                ->constrained('levels')
                ->nullOnDelete();

            $table->foreignId('module_id')
                ->nullable()
                ->constrained('modules')
                ->nullOnDelete();

            $table->foreignId('chapter_id')
                ->nullable()
                ->constrained('chapters')
                ->nullOnDelete();

            $table->foreignId('topic_id')
                ->constrained('topics')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [
                'open',
                'resolved',
                'reopened',
            ])->default('open');

            /*
            |--------------------------------------------------------------------------
            | META
            |--------------------------------------------------------------------------
            */

            $table->timestamp('last_message_at')
                ->nullable();

            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('resolved_at')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | TIMESTAMPS
            |--------------------------------------------------------------------------
            */

            $table->timestamps();

            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | UNIQUE
            |--------------------------------------------------------------------------
            */

            $table->unique([
                'user_id',
                'topic_id'
            ], 'support_user_topic_unique');

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */

            $table->index('status');

            $table->index('topic_id');

            $table->index('user_id');

            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_threads');
    }
};
