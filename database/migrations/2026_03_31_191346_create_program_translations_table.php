<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('program_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('program_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('language_code', 10);

            $table->string('title');
            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique(['program_id', 'language_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_translations');
    }
};