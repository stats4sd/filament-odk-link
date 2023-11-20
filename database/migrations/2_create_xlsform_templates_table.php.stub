<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('xlsform_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();

            $table->boolean('available')->default(0)->comment('Available to all users? If false, the form is only available to testers or admins.');

            // Who does the template belong to? (By default, this will be "the platform". This is to enable admins to test a draft version of the form before sharing with teams)
            $table->foreignId('owner_id')->nullable();
            $table->string('owner_type')->nullable();

            // odk draft details - Each template gets deployed as a draft for testing before it is shared with users.
            $table->string('odk_id')->nullable()->comment('The unique ID of the form on ODK service. If null, the form has not yet been pushed to ODK Central.');
            $table->string('odk_draft_token')->nullable()->comment('ODK Central only: The current draft token, required to generate a QR code for testing the draft in ODK Collect');
            $table->string('has_draft')->nullable()->comment('Does the form have a deployed draft?');
            $table->string('enketo_draft_id')->nullable()->comment('id component of the enketo version - pulled from the ODK service if supported/enabled');
            $table->boolean('draft_needs_updating')->default(0)->comment('Set to true if the form has been updated since the last draft was deployed to ODK Central');
            $table->text('odk_error')->nullable()->comment('If a xlsfile upload results in an ODK syntax error, it will be stored here. For working forms, this will be null');
            $table->string('odk_version_id')->nullable();



            // The full schema of the form, as a json object
            $table->json('schema')->nullable();

            // which dataset does this form's submissions populate?
            $table->foreignId('main_dataset_id')->nullable()->constrained('datasets')->nullOnDelete()->cascadeOnUpdate();

            // system stuff
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xlsform_templates');
    }
};
