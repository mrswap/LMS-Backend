<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('assessment_attempts', function (Blueprint $table) {

            // ⏱ time tracking
            $table->integer('time_taken')->nullable(); // seconds

            // 🧠 कैसे submit हुआ
            $table->enum('submit_type', [
                'manual',   // user clicked submit
                'timeout',  // auto submit due to timer
                'quit'      // user exited
            ])->default('manual');
        });
    }

    public function down()
    {
        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->dropColumn(['time_taken', 'submit_type']);
        });
    }
};
