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
        Schema::create('chapter_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('chapter_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('language_code', 10);

            $table->string('title');
            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique(['chapter_id', 'language_code']);
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chapter_translations');
    }
};
