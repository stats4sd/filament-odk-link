<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $teamTable = (new(config('filament-odk-link.models.team_model')))->getTable();

        /**
         * Table to store the relationship link between app users and xlsforms (which forms is each app user assigned to?)
         */
        Schema::create('odk_datasets', function (Blueprint $table) use ($teamTable) {
            $table->id();
            $table->foreignId('odk_project_id')->constrained();
            $table->foreignId('dataset_id')->constrained();
            $table->foreignId('owner_id')->constrained($teamTable);

            $table->string('name');
            $table->text('description')->nullable();

            $table->boolean('archived')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odk_datasets');
    }
};
