<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The form-owner ("Team") model is supplied by the host app, not the package.
// The test suite provides a minimal table so the package migrations that
// constrain against it (e.g. 002_create_xlsforms_table) can run.
//
// Named with a leading-zero prefix so it sorts before the package's
// `000_`/`001_`/`002_` migrations, which depend on this table existing.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
