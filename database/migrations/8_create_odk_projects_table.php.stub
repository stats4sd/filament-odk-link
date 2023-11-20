<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {

        Schema::create('odk_projects', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
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
        Schema::dropIfExists('odk_projects');
    }
};
