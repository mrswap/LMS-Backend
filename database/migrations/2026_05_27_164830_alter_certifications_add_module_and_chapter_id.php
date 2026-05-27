<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certifications', function (Blueprint $table) {

            /*
            |--------------------------------------------------
            | MODULE
            |--------------------------------------------------
            */

            if (!Schema::hasColumn('certifications', 'module_id')) {

                $table->foreignId('module_id')
                    ->nullable()
                    ->after('level_id')
                    ->constrained('modules')
                    ->nullOnDelete();
            }

            /*
            |--------------------------------------------------
            | CHAPTER
            |--------------------------------------------------
            */

            if (!Schema::hasColumn('certifications', 'chapter_id')) {

                $table->foreignId('chapter_id')
                    ->nullable()
                    ->after('module_id')
                    ->constrained('chapters')
                    ->nullOnDelete();
            }

            /*
            |--------------------------------------------------
            | MAKE EXISTING NULLABLE
            |--------------------------------------------------
            */

            $table->foreignId('program_id')
                ->nullable()
                ->change();

            $table->foreignId('level_id')
                ->nullable()
                ->change();

            $table->foreignId('topic_id')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('certifications', function (Blueprint $table) {

            if (Schema::hasColumn('certifications', 'chapter_id')) {

                $table->dropForeign(['chapter_id']);
                $table->dropColumn('chapter_id');
            }

            if (Schema::hasColumn('certifications', 'module_id')) {

                $table->dropForeign(['module_id']);
                $table->dropColumn('module_id');
            }
        });
    }
};
