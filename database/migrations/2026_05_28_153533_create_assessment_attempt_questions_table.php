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
        Schema::create('assessment_attempt_questions', function (Blueprint $table) {

            $table->id();

            $table->foreignId('attempt_id')
                ->constrained('assessment_attempts')
                ->cascadeOnDelete();

            $table->foreignId('question_id')
                ->constrained('assessment_questions')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique([
                'attempt_id',
                'question_id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_attempt_questions');
    }
};
