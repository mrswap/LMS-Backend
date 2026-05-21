<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('support_threads', function (Blueprint $table) {

            $table->boolean('ai_enabled')
                ->default(true)
                ->after('status');

            $table->timestamp('ai_last_reply_at')
                ->nullable()
                ->after('ai_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('support_threads', function (Blueprint $table) {

            $table->dropColumn([
                'ai_enabled',
                'ai_last_reply_at',
            ]);
        });
    }
};
