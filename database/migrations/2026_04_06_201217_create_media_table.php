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
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('description')->nullable();

            $table->enum('type', ['image', 'video', 'audio']);

            $table->string('file')->nullable(); // local file path
            $table->string('external_url')->nullable(); // youtube/vimeo

            $table->string('shortcode')->unique();

            $table->string('disk')->default('public'); // future S3 support

            $table->boolean('status')->default(1);

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
