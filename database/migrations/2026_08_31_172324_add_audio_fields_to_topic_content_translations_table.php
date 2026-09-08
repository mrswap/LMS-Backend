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
        Schema::table('topic_content_translations', function (Blueprint $table) {

            if (!Schema::hasColumn('topic_content_translations', 'audio_path')) {
                $table->string('audio_path')
                    ->nullable()
                    ->after('content');
            }

            if (!Schema::hasColumn('topic_content_translations', 'audio_generated_at')) {
                $table->timestamp('audio_generated_at')
                    ->nullable()
                    ->after('audio_path');
            }

            if (!Schema::hasColumn('topic_content_translations', 'audio_provider')) {
                $table->string('audio_provider')
                    ->nullable()
                    ->after('audio_generated_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topic_content_translations', function (Blueprint $table) {

            $columns = [];

            if (Schema::hasColumn('topic_content_translations', 'audio_path')) {
                $columns[] = 'audio_path';
            }

            if (Schema::hasColumn('topic_content_translations', 'audio_generated_at')) {
                $columns[] = 'audio_generated_at';
            }

            if (Schema::hasColumn('topic_content_translations', 'audio_provider')) {
                $columns[] = 'audio_provider';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};