<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topic_contents', function (Blueprint $table) {

            $table->string('audio_path')->nullable()->after('meta');

            $table->timestamp('audio_generated_at')
                ->nullable()
                ->after('audio_path');

            $table->string('audio_provider')
                ->nullable()
                ->after('audio_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('topic_contents', function (Blueprint $table) {

            $table->dropColumn([
                'audio_path',
                'audio_generated_at',
                'audio_provider',
            ]);
        });
    }
};
