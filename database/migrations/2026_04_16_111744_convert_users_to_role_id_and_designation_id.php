<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |-----------------------------------------
        | STEP 1 — ADD NEW COLUMNS
        |-----------------------------------------
        */
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->after('password');
            $table->unsignedBigInteger('designation_id')->nullable()->after('role_id');
        });

        /*
        |-----------------------------------------
        | STEP 2 — MIGRATE ROLE DATA (ENUM → ID)
        |-----------------------------------------
        */
        $roles = DB::table('roles')->pluck('id', 'name');

        foreach ($roles as $name => $id) {
            DB::table('users')
                ->where('role', $name)
                ->update(['role_id' => $id]);
        }

        /*
        |-----------------------------------------
        | STEP 3 — MIGRATE DESIGNATION DATA
        |-----------------------------------------
        */
        $designations = DB::table('designations')->pluck('id', 'label');

        foreach ($designations as $label => $id) {
            DB::table('users')
                ->where('designation', $label)
                ->update(['designation_id' => $id]);
        }

        /*
        |-----------------------------------------
        | STEP 4 — REMOVE OLD COLUMNS
        |-----------------------------------------
        */
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'designation']);
        });

        /*
        |-----------------------------------------
        | STEP 5 — ADD FOREIGN KEYS
        |-----------------------------------------
        */
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('designation_id')->references('id')->on('designations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        /*
        |-----------------------------------------
        | ROLLBACK (OPTIONAL BUT SAFE)
        |-----------------------------------------
        */
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['superadmin', 'staff', 'sales'])->default('sales');
            $table->string('designation')->nullable();
        });

        // reverse mapping (optional basic)
        $roles = DB::table('roles')->pluck('name', 'id');

        foreach ($roles as $id => $name) {
            DB::table('users')
                ->where('role_id', $id)
                ->update(['role' => $name]);
        }

        $designations = DB::table('designations')->pluck('label', 'id');

        foreach ($designations as $id => $label) {
            DB::table('users')
                ->where('designation_id', $id)
                ->update(['designation' => $label]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropForeign(['designation_id']);
            $table->dropColumn(['role_id', 'designation_id']);
        });
    }
};
