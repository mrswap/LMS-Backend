
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | ASSESSMENTS
        |--------------------------------------------------------------------------
        */

        Schema::table('assessments', function (Blueprint $table) {

            $table->decimal(
                'passing_score',
                10,
                2
            )->change();

            $table->decimal(
                'total_marks',
                10,
                2
            )->change();
        });

        /*
        |--------------------------------------------------------------------------
        | ASSESSMENT QUESTIONS
        |--------------------------------------------------------------------------
        */

        Schema::table('assessment_questions', function (Blueprint $table) {

            $table->decimal(
                'marks',
                10,
                2
            )->default(1)->change();
        });

        /*
        |--------------------------------------------------------------------------
        | ASSESSMENT ANSWERS
        |--------------------------------------------------------------------------
        */

        Schema::table('assessment_answers', function (Blueprint $table) {

            $table->decimal(
                'marks_snapshot',
                10,
                2
            )->nullable()->change();

            $table->decimal(
                'marks_obtained',
                10,
                2
            )->default(0)->change();
        });

        /*
        |--------------------------------------------------------------------------
        | ASSESSMENT ATTEMPTS
        |--------------------------------------------------------------------------
        */

        Schema::table('assessment_attempts', function (Blueprint $table) {

            $table->decimal(
                'score',
                10,
                2
            )->nullable()->change();

            $table->decimal(
                'percentage',
                10,
                2
            )->nullable()->change();
        });

        /*
        |--------------------------------------------------------------------------
        | CERTIFICATIONS
        |--------------------------------------------------------------------------
        */

        Schema::table('certifications', function (Blueprint $table) {

            $table->decimal(
                'score',
                10,
                2
            )->nullable()->change();

            $table->decimal(
                'percentage',
                10,
                2
            )->nullable()->change();
        });
    }

    public function down(): void
    {
        //
    }
};
