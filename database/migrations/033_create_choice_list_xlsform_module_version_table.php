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
        Schema::create('choice_list_xlsform_module_version', function (Blueprint $table) {
            $table->id();
            $table->foreignId('choice_list_id')->constrained('choice_lists', indexName: 'clxmv_choice_list_id')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('xlsform_module_version_id')->constrained('xlsform_module_versions', indexName: 'clxmv_xlsform_module_version_id')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('choice_list_xlsform_module_version');
    }
};
