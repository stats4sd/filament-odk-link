<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stats4sd\FilamentOdkLink\Services\XlsformInstanceNameResolver;

function resolveInstanceName(?string $expression, array $questionNamesPerModule): string
{
    $template = makeXlsformTemplate();
    $template->updateQuietly(['instance_name' => $expression]);

    $xlsform = makeXlsformWithQuestions($template, $questionNamesPerModule);

    return (new XlsformInstanceNameResolver)->resolve($xlsform);
}

it('uses the default when the template has no instance_name', function () {
    Log::shouldReceive('warning')->never();

    expect(resolveInstanceName(null, ['meta' => ['interviewer_name']]))->toBe('"instance"');
});

it('uses the default when the template instance_name is blank', function () {
    expect(resolveInstanceName('  ', ['meta' => ['interviewer_name']]))->toBe('"instance"');
});

it('uses the template expression when every referenced question is in the form', function () {
    $expression = 'concat("GLOBAL:", ${interviewer_name}, \' - \', ${starttime_auto})';

    expect(resolveInstanceName($expression, ['meta' => ['interviewer_name', 'starttime_auto']]))->toBe($expression);
});

it('uses an expression that has no references', function () {
    expect(resolveInstanceName('"fixed name"', ['meta' => ['interviewer_name']]))->toBe('"fixed name"');
});

it('finds referenced questions in any module of the form', function () {
    $expression = 'concat(${interviewer_name}, ${farm_var_participant_name})';

    $instanceName = resolveInstanceName($expression, [
        'meta' => ['interviewer_name'],
        'farm info' => ['farm_var_participant_name'],
    ]);

    expect($instanceName)->toBe($expression);
});

it('counts a question that is referenced several times only once', function () {
    $expression = 'concat(${interviewer_name}, ${interviewer_name})';

    expect(resolveInstanceName($expression, ['meta' => ['interviewer_name']]))->toBe($expression);
});

it('falls back to the default and logs the missing questions when one is not in the form', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context) => $context['missing_names'] === ['farm_var_participant_name']);

    $instanceName = resolveInstanceName(
        'concat(${interviewer_name}, ${farm_var_participant_name})',
        ['meta' => ['interviewer_name']],
    );

    expect($instanceName)->toBe('"instance"');
});

it('does not count a question that sits in a module the form does not use', function () {
    $template = makeXlsformTemplate();
    $template->updateQuietly(['instance_name' => 'concat(${farm_var_participant_name})']);

    $notSelectedVersion = addModuleVersion($template, 'farm info');
    DB::table('survey_rows')->insert([
        'xlsform_module_version_id' => $notSelectedVersion->id,
        'name' => 'farm_var_participant_name',
        'type' => 'calculate',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $xlsform = makeXlsformWithQuestions($template, ['meta' => ['interviewer_name']]);

    Log::shouldReceive('warning')->once();

    expect((new XlsformInstanceNameResolver)->resolve($xlsform))->toBe('"instance"');
});
