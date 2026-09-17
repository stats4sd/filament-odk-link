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
        Schema::create('continents', function (Blueprint $table) {
            $table->string('id')->primary(); // m49 code
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('regions', function (Blueprint $table) {
            $table->string('id')->primary(); // m49 code
            $table->string('continent_id')->constrained('continents');
            $table->string('parent_id')->nullable()->constrained('regions');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('region_id')->constrained('regions');
            $table->string('iso_alpha2', 2);
            $table->string('iso_alpha3', 3);
            $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
