<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('level_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('language_code', 10);

            $table->string('title');
            $table->text('description')->nullable();

            $table->timestamps();

            // prevent duplicate language per level
            $table->unique(['level_id', 'language_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_translations');
    }
};