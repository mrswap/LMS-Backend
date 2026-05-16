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
        Schema::create('import_logs', function (Blueprint $table) {

            $table->id();

            $table->foreignId('program_id')
                ->constrained('programs')
                ->cascadeOnDelete();

            $table->foreignId('level_id')
                ->constrained('levels')
                ->cascadeOnDelete();

            $table->string('status')
                ->default('pending');

            $table->longText('raw_html')
                ->nullable();

            $table->json('meta')
                ->nullable();

            $table->longText('error')
                ->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
