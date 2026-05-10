<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_questions', function (Blueprint $table) {

            $table->decimal('marks', 8, 2)
                ->default(0)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_questions', function (Blueprint $table) {

            $table->integer('marks')
                ->default(0)
                ->change();
        });
    }
};
