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

        // create single entry to represent the current platform for xlsform_template drafts.
        Platform::create();
    }


    public function down(): void
    {
        Schema::dropIfExists('platforms');
    }
};
