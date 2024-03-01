<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /**
         * Table to store the individual team's forms (many-many pivot table between Xlsform and Team)
         */
        Schema::create('xlsforms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('xlsform_template_id')->constrained('xlsform_templates');

            $table->foreignId('owner_id');
            $table->string('owner_type');
            // direct link to the owner's project on ODK Central.
            $table->foreignId('odk_project_id')->nullable();

            $table->string('title')->nullable()->comment('If null, the system by default retrieves a title in the format $ownerName - $xlsformTemplateTitle');

            // ODK deployment stuff
            $table->string('odk_id')->nullable()->comment('The unique ID of the form on ODK service. If null, the form has not yet been pushed to ODK Central.');
            $table->string('odk_draft_token')->nullable()->comment('ODK Central only: The current draft token, required to generate a QR code for testing the draft in ODK Collect');
            $table->string('odk_version_id')->nullable()->comment('current or most recently deployed version on the ODK service. If null, the form has not yet been deployed on ODK Central.');
            $table->string('has_draft')->nullable()->comment('Does the form have a deployed draft?');
            $table->string('is_active')->nullable()->comment('is the form active and accepting submissions?');
            $table->string('enketo_draft_id')->nullable()->comment('unique id - part of the url to the enketo version - pulled from the ODK service if supported/enabled');
            $table->string('enketo_id')->nullable()->comment('unique id for the enketo version - pulled from the ODK service if supported/enabled');
            $table->boolean('processing')->default(0)->comment('Is the form currently being processed? (helps to avoid duplicate deployments)');
            $table->text('odk_error')->nullable()->comment('If an xlsfile upload returns an error from the ODK Aggregate service, it will be stored here');

            // The full schema of the form, as a json object
            $table->json('schema')->nullable();

            // Does this form use the latest available template?
            $table->boolean('has_latest_template')->default(1);

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xlsforms');
    }
};
