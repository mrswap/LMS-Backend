<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE media
            MODIFY type VARCHAR(50)
            NOT NULL DEFAULT 'file'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE media
            MODIFY type ENUM(
                'image',
                'video',
                'audio',
                'document'
            )
            NOT NULL DEFAULT 'image'
        ");
    }
};
