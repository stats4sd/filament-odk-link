<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        $teamTable = (new (config('filament-odk-link.models.team_model')))->getTable();

        Schema::create('entities', function (Blueprint $table) use ($teamTable) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('datasets');
            $table->foreignId('parent_id')->nullable()->constrained('entities')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('owner_id')->constrained($teamTable)->cascadeOnDelete()->cascadeOnUpdate();
            $table->nullableMorphs('model');
            $table->foreignId('submission_id')->nullable()->constrained('submissions')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('entities');
    }
};
