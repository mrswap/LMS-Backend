<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_progress', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->foreignId('program_id')->nullable();
            $table->foreignId('level_id')->nullable();
            $table->foreignId('module_id')->nullable();
            $table->foreignId('chapter_id')->nullable();
            $table->foreignId('topic_id')->nullable();

            $table->boolean('is_unlocked')->default(false);
            $table->boolean('is_completed')->default(false);

            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'topic_id']); // 🔥 IMPORTANT
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_progress');
    }
};
