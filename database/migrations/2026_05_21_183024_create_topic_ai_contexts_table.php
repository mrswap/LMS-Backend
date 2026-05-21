<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('topic_ai_contexts', function (Blueprint $table) {

            $table->id();

            $table->foreignId('topic_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->longText('context');

            $table->integer('context_length')
                ->default(0);

            $table->timestamp('generated_at')
                ->nullable();

            $table->timestamps();

            /*
            |------------------------------------------------------------------
            | INDEXES
            |------------------------------------------------------------------
            */

            $table->index('generated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_ai_contexts');
    }
};
