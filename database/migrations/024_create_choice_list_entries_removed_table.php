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
        $teamTable = (new (config('filament-odk-link.models.team_model')))->getTable();

        Schema::create('choice_list_entries_removed_owner', function (Blueprint $table) use ($teamTable) {
            $table->id();
            $table->foreignId('choice_list_entry_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('owner_id')->constrained($teamTable)->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('choice_list_entries_removed_owner');
    }
};
