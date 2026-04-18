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
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('assessmentable_id');
            $table->string('assessmentable_type');

            $table->enum('type', ['topic', 'level']);

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('file')->nullable();

            $table->integer('duration')->nullable();
            $table->integer('passing_score');
            $table->integer('total_marks');

            $table->boolean('status')->default(1);
            $table->unsignedBigInteger('created_by');

            $table->timestamps();

            $table->index(['assessmentable_id', 'assessmentable_type']);
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_options');
    }
};
