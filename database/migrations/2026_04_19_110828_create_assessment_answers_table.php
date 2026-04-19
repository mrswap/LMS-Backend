<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('assessment_answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attempt_id')
                ->constrained('assessment_attempts')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('question_id');

            $table->text('question_text_snapshot');
            $table->json('options_snapshot');
            $table->unsignedBigInteger('correct_option_id_snapshot')->nullable();
            $table->integer('marks_snapshot');

            $table->unsignedBigInteger('selected_option_id')->nullable();

            $table->boolean('is_correct')->default(false);
            $table->integer('marks_obtained')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_answers');
    }
};
