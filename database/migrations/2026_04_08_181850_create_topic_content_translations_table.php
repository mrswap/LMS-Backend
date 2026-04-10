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
        Schema::create('topic_content_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('topic_content_id')
                ->constrained('topic_contents')
                ->cascadeOnDelete();

            $table->string('language_code');

            $table->string('title')->nullable();
            $table->longText('content')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topic_content_translations');
    }
};
