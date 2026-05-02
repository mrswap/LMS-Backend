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
        $tables = [
            'programs',
            'levels',
            'modules',
            'chapters',
            'topics',
            'topic_contents',

            'assessments',
            'assessment_questions',
            'assessment_options',
            'assessment_attempts',
            'assessment_answers',
            'assessment_feedback',

            'users',
            'roles',
            'designations',

            'faqs',
            'media',
            'languages',
            'certifications',
            'certificate_settings',
            'contact_messages',
            'settings',
            'smtp_settings',
            'audit_logs',

            'program_translations',
            'level_translations',
            'module_translations',
            'chapter_translations',
            'topic_translations',
            'topic_content_translations',
            'faq_translations',

            'user_progress',
            'user_content_progress'
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('all_tables', function (Blueprint $table) {
            //
        });
    }
};
