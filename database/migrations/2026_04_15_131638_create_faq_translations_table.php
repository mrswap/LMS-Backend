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
        Schema::create('faq_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('faq_id')->constrained()->cascadeOnDelete();

            $table->string('language_code', 10);
            $table->string('question');
            $table->longText('answer'); // HTML editor content

            $table->timestamps();

            $table->unique(['faq_id', 'language_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faq_translations');
    }
};
