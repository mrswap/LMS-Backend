<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_questions', function (Blueprint $table) {

            $table->boolean('is_case')
                ->default(false)
                ->after('question_text');

            $table->string('case_title')
                ->nullable()
                ->after('is_case');

            $table->longText('case_text')
                ->nullable()
                ->after('case_title');

            $table->integer('case_order')
                ->nullable()
                ->after('case_text');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_questions', function (Blueprint $table) {

            $table->dropColumn([
                'is_case',
                'case_title',
                'case_text',
                'case_order'
            ]);
        });
    }
};