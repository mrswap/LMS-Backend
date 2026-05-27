<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE assessments
            MODIFY COLUMN type
            ENUM('topic','chapter','module','level')
            NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE assessments
            MODIFY COLUMN type
            ENUM('topic','level')
            NOT NULL
        ");
    }
};
