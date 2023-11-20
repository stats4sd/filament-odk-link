<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /**
         * Table to store all App Users created for projects
         */
        Schema::create('app_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('odk_project_id')->constrained('odk_projects');
            $table->string('display_name');
            $table->string('type');
            $table->string('token');

            $table->boolean('can_access_all_forms')->default(0)->comment('App users might be assigned the "admin" role in a project; which automatically gives them access to all forms. Otherwise, they are manually assigned to specific forms via the app_user_assignments table');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_users');
    }
};
