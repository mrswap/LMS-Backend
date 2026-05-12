<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | OPTION 1 (RECOMMENDED)
        |--------------------------------------------------------------------------
        | Convert ENUM -> STRING
        | Future-safe for LMS architecture
        |--------------------------------------------------------------------------
        */

        Schema::table('media', function (Blueprint $table) {

            // logical media type
            $table->string('type', 50)
                ->default('file')
                ->change();

            // actual mime type
            $table->string('mime_type')
                ->nullable()
                ->after('type');

            // file extension
            $table->string('extension', 20)
                ->nullable()
                ->after('mime_type');

            // original uploaded name
            $table->string('original_name')
                ->nullable()
                ->after('extension');

            // file size in bytes
            $table->unsignedBigInteger('file_size')
                ->nullable()
                ->after('original_name');
        });

        /*
        |--------------------------------------------------------------------------
        | Normalize Existing Data
        |--------------------------------------------------------------------------
        */

        DB::table('media')
            ->whereNull('type')
            ->update([
                'type' => 'file'
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {

            $table->dropColumn([
                'mime_type',
                'extension',
                'original_name',
                'file_size',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Revert type back to ENUM
        |--------------------------------------------------------------------------
        | WARNING:
        | This may fail if newer values exist.
        |--------------------------------------------------------------------------
        */

        DB::statement("
            ALTER TABLE media
            MODIFY type ENUM(
                'image',
                'video',
                'audio'
            ) NOT NULL DEFAULT 'image'
        ");
    }
};
