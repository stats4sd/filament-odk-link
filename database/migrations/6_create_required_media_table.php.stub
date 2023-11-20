<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /**
         * Table to store all ODK raw submissions that get pulled from ODK Central
         */
        Schema::create('required_media', function (Blueprint $table) {

            $table->id();
            $table->foreignId('dataset_id')->nullable()->constrained('datasets');
            $table->foreignId('xlsform_template_id')->constrained('xlsform_templates')->cascadeOnDelete()->cascadeOnUpdate();

            $table->string('name');
            $table->string('type');

            // will the media uploaded to the ODK form be a static file? (If false, it will be generated from a database table/view.
            $table->boolean('is_static')->default(true);
            $table->boolean('exists_on_odk')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('required_media');
    }
};
