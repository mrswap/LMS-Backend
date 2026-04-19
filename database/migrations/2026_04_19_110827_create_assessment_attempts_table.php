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
        Schema::create('assessment_attempts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('assessment_id');

            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();

            $table->integer('score')->nullable();
            $table->decimal('percentage', 5, 2)->nullable();

            $table->enum('status', ['in_progress', 'completed', 'passed', 'failed'])
                ->default('in_progress');

            $table->timestamps();

            $table->index(['user_id', 'assessment_id']);
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_attempts');
    }
};
