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
        $teamTable = (new (config('filament-odk-link.models.form_owner')))->getTable();

        Schema::create('language_owner', function (Blueprint $table) use ($teamTable) {
            $table->id();
            $table->foreignId('language_id')->constrained('languages')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('owner_id')->constrained($teamTable)->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('locale_id')->nullable()->constrained('locales')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('language_owner');
    }
};
