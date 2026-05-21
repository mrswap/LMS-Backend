<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'topic_ai_contexts',
            function (Blueprint $table) {

                $table->softDeletes();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'topic_ai_contexts',
            function (Blueprint $table) {

                $table->dropSoftDeletes();
            }
        );
    }
};
