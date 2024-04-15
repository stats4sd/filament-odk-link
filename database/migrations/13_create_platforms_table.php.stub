<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Platform;

return new class extends Migration {
    public function up(): void
    {

        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('platforms');
    }
};
