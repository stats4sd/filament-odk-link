<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /**
         * Table to store the relationship link between app users and xlsforms (which forms is each app user assigned to?)
         */
        Schema::create('app_user_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->foreignId('app_user_id')->constrained('app_users');
            $table->foreignId('xlsform_id')->constrained('xlsforms');
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('app_user_assignments');
    }
};
