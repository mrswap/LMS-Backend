<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {

            $table->enum('type', [
                'all',
                'content',
                'quiz',
                'exam',
                'both'
            ])->default('all')
                ->after('level_id');
        });
    }

    public function down(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {

            $table->dropColumn('type');
        });
    }
};
