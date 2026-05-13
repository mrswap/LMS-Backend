<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_messages', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | THREAD
            |--------------------------------------------------------------------------
            */

            $table->foreignId('thread_id')
                ->constrained('support_threads')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | SENDER
            |--------------------------------------------------------------------------
            */

            $table->foreignId('sender_id')
                ->constrained('users')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | MESSAGE
            |--------------------------------------------------------------------------
            */

            $table->longText('message');

            $table->string('attachment')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | ROLE FLAG
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_admin')
                ->default(false);

            /*
            |--------------------------------------------------------------------------
            | READ STATUS
            |--------------------------------------------------------------------------
            */

            $table->timestamp('read_at')
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
            | INDEXES
            |--------------------------------------------------------------------------
            */

            $table->index('thread_id');

            $table->index('sender_id');

            $table->index('is_admin');

            $table->index('read_at');

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};
