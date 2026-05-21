<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {

            $table->boolean('is_ai')
                ->default(false)
                ->after('is_admin');

            $table->string('ai_provider')
                ->nullable()
                ->after('is_ai');

            $table->json('ai_meta')
                ->nullable()
                ->after('ai_provider');
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {

            $table->dropColumn([
                'is_ai',
                'ai_provider',
                'ai_meta',
            ]);
        });
    }
};
