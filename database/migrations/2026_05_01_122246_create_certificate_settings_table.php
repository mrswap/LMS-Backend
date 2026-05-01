<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('certificate_settings', function (Blueprint $table) {
            $table->id();

            $table->string('company_name')->nullable();
            $table->string('company_logo')->nullable();
            $table->string('tagline')->nullable();

            $table->string('certificate_heading')->nullable();

            $table->string('signer_name')->nullable();
            $table->string('signer_designation')->nullable();
            $table->string('signer_signature')->nullable();

            $table->longText('content')->nullable(); 

            $table->string('footer_text')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_settings');
    }
};
