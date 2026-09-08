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
        Schema::create('xlsform_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('xlsform_template_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('label');
            $table->string('name');
            $table->unsignedInteger('default_order')->nullable();
            $table->boolean('can_be_extended')->default(false);
            $table->boolean('can_be_replaced')->default(false);
            $table->json('row_names')->nullable();
            $table->timestamps();

            $table->unique(['xlsform_template_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xlsform_template_modules');
    }
};
