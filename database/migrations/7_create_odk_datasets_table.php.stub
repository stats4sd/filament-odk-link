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
        Schema::create('odk_datasets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id');
            $table->string('owner_type');

            $table->string('name');
            $table->text('description')->nullable();

            $table->boolean('archived')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odk_datasets');
    }
};
