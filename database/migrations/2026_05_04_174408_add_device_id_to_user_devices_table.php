<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            $table->string('device_id')->after('user_id');

            // 🔥 important (unique per user + device)
            $table->unique(['user_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'device_id']);
            $table->dropColumn('device_id');
        });
    }
};
