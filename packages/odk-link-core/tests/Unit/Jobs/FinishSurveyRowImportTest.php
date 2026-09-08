<?php

use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Jobs\FinishSurveyRowImport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;

it('marks every survey row of the model as no longer updated_during_import', function () {
    $version = addModuleVersion(makeXlsformTemplate(), 'mod');

    foreach (['q1', 'q2'] as $i => $name) {
        DB::table('survey_rows')->insert([
            'row_number' => $i + 1,
            'name' => $name,
            'type' => 'text',
            'xlsform_module_version_id' => $version->id,
            'updated_during_import' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    (new FinishSurveyRowImport($version))->handle();

    expect(SurveyRow::where('xlsform_module_version_id', $version->id)->where('updated_during_import', true)->count())
        ->toBe(0);
});
