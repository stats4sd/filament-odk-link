<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities');
            $table->foreignId('dataset_variable_name')->constrained('dataset_variables', 'name')->cascadeOnUpdate()->cascadeOnDelete();
            $table->text('value');
            $table->timestamps();

            $table->index('dataset_variable_name');
            $table->unique(['entity_id', 'dataset_variable_name']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_values');
    }
};
