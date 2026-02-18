<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dataset_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('datasets');
            $table->string('name');
            $table->string('label');
            $table->string('type')->nulllable();
            $table->string('value_type')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataset_variables');
    }
};
