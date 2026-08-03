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
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->string('odk_id')->unique()->comment('The ODK Central ID of the submission.');
            $table->string('odk_latest_version_id')->nullable()->comment('If the submission has been edited on ODK, this is the latest version ID.');
            $table->foreignId('xlsform_version_id')->constrained('xlsform_versions');
            $table->nullableMorphs('primary_data_subject', 'subject');
            $table->timestamp('submitted_at');
            $table->string('submitted_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->longtext('content'); // This is explicitly not json so the ordering of variables is preserved (at the expense of not being able to query the content in SQL);

            $table->boolean('draft_data')->default(0);
            $table->boolean('test_data')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
