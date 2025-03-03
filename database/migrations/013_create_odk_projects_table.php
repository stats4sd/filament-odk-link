<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        $teamTable = (new(config('filament-odk-link.models.team_model')))->getTable();

        Schema::create('odk_projects', function (Blueprint $table) use ($teamTable) {
            $table->unsignedBigInteger('id')->primary();
            $table->foreignId('owner_id')->constrained($teamTable);

            $table->string('name');
            $table->text('description')->nullable();

            $table->boolean('archived')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odk_projects');
    }
};
