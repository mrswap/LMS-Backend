<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'programs',
            'levels',
            'modules',
            'chapters',
            'topics',
            'topic_contents',
        ];

        foreach ($tables as $table) {

            Schema::table($table, function (Blueprint $table) {

                $table->enum('publish_status', [
                    'draft',
                    'published',
                    'unpublished'
                ])
                    ->default('draft')
                    ->after('status');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | SYNC OLD STATUS DATA
        |--------------------------------------------------------------------------
        */

        DB::statement("
            UPDATE programs
            SET publish_status =
                CASE
                    WHEN status = 1 THEN 'published'
                    ELSE 'draft'
                END
        ");

        DB::statement("
            UPDATE levels
            SET publish_status =
                CASE
                    WHEN status = 1 THEN 'published'
                    ELSE 'draft'
                END
        ");

        DB::statement("
            UPDATE modules
            SET publish_status =
                CASE
                    WHEN status = 1 THEN 'published'
                    ELSE 'draft'
                END
        ");

        DB::statement("
            UPDATE chapters
            SET publish_status =
                CASE
                    WHEN status = 1 THEN 'published'
                    ELSE 'draft'
                END
        ");

        DB::statement("
            UPDATE topics
            SET publish_status =
                CASE
                    WHEN status = 1 THEN 'published'
                    ELSE 'draft'
                END
        ");

        DB::statement("
            UPDATE topic_contents
            SET publish_status =
                CASE
                    WHEN status = 1 THEN 'published'
                    ELSE 'draft'
                END
        ");
    }

    public function down(): void
    {
        $tables = [
            'programs',
            'levels',
            'modules',
            'chapters',
            'topics',
            'topic_contents',
        ];

        foreach ($tables as $table) {

            Schema::table($table, function (Blueprint $table) {

                $table->dropColumn('publish_status');
            });
        }
    }
};
