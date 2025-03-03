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
        $teamTable  = (new(config('filament-odk-link.models.team_model')))->getTable();

        Schema::create('choice_list_entries', function (Blueprint $table) use ($teamTable) {
            $table->id();
            $table->foreignId('choice_list_id')->constrained()->onDelete('cascade');

            // entries might be global; or localised (localised ones have an owner)
            $table->foreignId('owner_id')->constrained($teamTable);
            $table->string('name');
            $table->json('properties')->nullable();
            $table->string('cascade_filter')->nullable();
            $table->boolean('updated_during_import')->default(0);
            $table->timestamps();

            $table->unique(['name', 'choice_list_id', 'cascade_filter'], 'unique_name_choice_list_id');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('choice_list_entries');
    }
};
