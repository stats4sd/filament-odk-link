<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xlsform_templates', function (Blueprint $table) {
            $table->text('instance_name')->nullable()->after('main_dataset_id')->comment('XLSForm settings.instance_name expression, read from the uploaded template. If null, the export uses the default.');
            $table->string('default_language')->nullable()->after('instance_name')->comment('XLSForm settings.default_language, read from the uploaded template. If null, the export omits it.');
        });
    }

    public function down(): void
    {
        Schema::table('xlsform_templates', function (Blueprint $table) {
            $table->dropColumn(['instance_name', 'default_language']);
        });
    }
};
