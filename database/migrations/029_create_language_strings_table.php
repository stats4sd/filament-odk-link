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
        Schema::create('language_strings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('locale_id')->constrained('locales')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('language_string_type_id')->constrained('language_string_types')->cascadeOnDelete()->cascadeOnUpdate();
            $table->morphs('linked_entry');
            $table->text('text');

            $table->boolean('updated_during_import')->default(false); // used to track if the row was updated during import of a new version of the XlsformTemplate file;
            $table->timestamps();

            $table->unique(['locale_id', 'linked_entry_id', 'linked_entry_type', 'language_string_type_id'], 'unique_language_string');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('language_strings');
    }
};
