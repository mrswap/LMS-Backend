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
        Schema::create('assessment_feedback', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();

            $table->tinyInteger('rating'); // 1 to 5
            $table->text('review')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'attempt_id']); // 🔥 one feedback per attempt
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_feedback');
    }
};
