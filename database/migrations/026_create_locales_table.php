<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $teamTable = (new(config('filament-odk-link.models.team_model')))->getTable();

        Schema::create('locales', function (Blueprint $table) use ($teamTable) {
            $table->id();
            $table->foreignId('language_id')->constrained('languages')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('creator_id')->nullable()->constrained($teamTable)->cascadeOnDelete()->cascadeOnUpdate();

            $table->boolean('is_default')->default(false);
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locales');
    }
};
