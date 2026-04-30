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
        Schema::create('certifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();

        
            $table->enum('type', ['topic', 'level']);

            // 🔥 audit link
            $table->foreignId('assessment_attempt_id')->constrained()->cascadeOnDelete();

            $table->string('certificate_id')->unique();

            $table->integer('score')->nullable();
            $table->float('percentage')->nullable();

            $table->timestamp('issued_at')->nullable();

            $table->boolean('status')->default(true);

            $table->string('file')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certifications');
    }
};
