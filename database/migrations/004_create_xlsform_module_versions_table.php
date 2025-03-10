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

        Schema::create('xlsform_module_versions', function (Blueprint $table) use ($teamTable) {
            $table->id();
            $table->foreignId('xlsform_module_id')->nullable()->constrained('xlsform_modules')->cascadeOnUpdate()->nullOnDelete();

            // A team might add their own custom xlsform version
            $table->foreignId('owner_id')->nullable()->constrained($teamTable)->cascadeOnUpdate()->nullOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['xlsform_module_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xlsform_module_versions');
    }
};
