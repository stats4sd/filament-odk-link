<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates_entity_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('xlsform_template_id')->constrained('xlsform_templates')->cascadeOnDelete();
            $table->string('list_name');
            $table->string('label_expression');
            $table->string('odk_entity_id_expression');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates_entity_lists');
    }
};
