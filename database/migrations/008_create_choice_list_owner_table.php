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
        $teamTable = (new (config('filament-odk-link.models.team_model')))->getTable();

        Schema::create('choice_list_owner', function (Blueprint $table) use ($teamTable) {
            $table->id();
            $table->foreignId('owner_id')->constrained($teamTable)->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('choice_list_id')->constrained('choice_lists', 'id')->cascadeOnDelete()->cascadeOnUpdate();
            $table->boolean('is_complete')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_lookup_tables');
    }
};
