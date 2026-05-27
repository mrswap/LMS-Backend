<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
            | MAKE EXISTING IDS NULLABLE
            |--------------------------------------------------
            */

            $table->unsignedBigInteger('program_id')
                ->nullable()
                ->change();

            $table->unsignedBigInteger('level_id')
                ->nullable()
                ->change();

            $table->unsignedBigInteger('topic_id')
                ->nullable()
                ->change();

            /*
            |--------------------------------------------------
            | STATUS DEFAULT
            |--------------------------------------------------
            */

            $table->boolean('status')
                ->default(true)
                ->change();
        });

        /*
        |--------------------------------------------------
        | UPDATE ENUM TYPE
        |--------------------------------------------------
        */

        DB::statement("
            ALTER TABLE certifications
            MODIFY COLUMN type ENUM(
                'topic',
                'chapter',
                'module',
                'level'
            ) NOT NULL DEFAULT 'topic'
        ");
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------
        | REVERT ENUM
        |--------------------------------------------------
        */

        DB::statement("
            ALTER TABLE certifications
            MODIFY COLUMN type ENUM(
                'topic',
                'level'
            ) NOT NULL DEFAULT 'topic'
        ");

        Schema::table('certifications', function (Blueprint $table) {

            /*
            |--------------------------------------------------
            | DROP CHAPTER
            |--------------------------------------------------
            */

            if (Schema::hasColumn('certifications', 'chapter_id')) {

                $table->dropForeign(['chapter_id']);

                $table->dropColumn('chapter_id');
            }

            /*
            |--------------------------------------------------
            | DROP MODULE
            |--------------------------------------------------
            */

            if (Schema::hasColumn('certifications', 'module_id')) {

                $table->dropForeign(['module_id']);

                $table->dropColumn('module_id');
            }
        });
    }
};
