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
        Schema::create('module_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('module_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('language_code', 10);

            $table->string('title');
            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique(['module_id', 'language_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_translations');
    }
};
