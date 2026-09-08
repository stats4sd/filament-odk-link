<?php

use Illuminate\Support\Facades\Bus;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Stats4sd\FilamentOdkLink\Jobs\FinishChoiceListEntryImport;
use Stats4sd\FilamentOdkLink\Jobs\FinishSurveyRowImport;
use Stats4sd\FilamentOdkLink\Jobs\FinishXlsformTemplateImport;
use Stats4sd\FilamentOdkLink\Jobs\ImportAllLanguageStrings;
use Stats4sd\FilamentOdkLink\Jobs\LinkModuleVersionToLocales;
use Stats4sd\FilamentOdkLink\Jobs\PrepareSurveyRowPaths;
use Stats4sd\FilamentOdkLink\Listeners\HandleXlsformTemplateAdded;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Tests\Models\Team;

$fixture = fn () => __DIR__ . '/../../fixtures/valid-form.xlsx';

it('ignores media added to models that are not templates or module versions', function () {
    // A Media whose owning model is a Team must not trigger any import work.
    $media = new Media;
    $media->setRelation('model', new Team(['name' => 'Acme']));

    Bus::fake();

    (new HandleXlsformTemplateAdded)->handle(new MediaHasBeenAddedEvent($media));

    Bus::assertNothingDispatched();
    expect(XlsformModule::count())->toBe(0);
});

it('creates the template modules (and default versions) from the workbook', function () use ($fixture) {
    $template = makeXlsformTemplate('Valid Test Form');

    $defaultVersions = (new HandleXlsformTemplateAdded)->createModules($fixture(), $template);

    expect($template->xlsformModules()->count())->toBe(3)
        ->and($template->xlsformModules()->pluck('name'))->toContain('demographics', 'repeats')
        // one default module version returned per module
        ->and($defaultVersions)->toHaveCount(3)
        ->and($defaultVersions->every(fn ($v) => $v->is_default))->toBeTrue();
});

it('queues the import-completion chain in order for a template', function () use ($fixture) {
    $template = makeXlsformTemplate('Valid Test Form');

    Bus::fake();

    (new HandleXlsformTemplateAdded)->processXlsformTemplate($fixture(), $template);

    // maatwebsite/excel wraps the workbook read in its own QueueImport job and
    // appends our completion jobs to that job's chain. Pull the chained jobs out
    // and keep only this package's, asserting they run in the declared order.
    $bus = Bus::getFacadeRoot();
    $commandsProp = (new ReflectionClass($bus))->getProperty('commands');
    $commandsProp->setAccessible(true);

    $packageJobs = [];
    foreach ($commandsProp->getValue($bus) as $instances) {
        foreach ($instances as $command) {
            $chainedProp = (new ReflectionObject($command))->getProperty('chained');
            $chainedProp->setAccessible(true);
            foreach ((array) $chainedProp->getValue($command) as $serialized) {
                $job = unserialize($serialized);
                if (str_starts_with($job::class, 'Stats4sd\\FilamentOdkLink\\Jobs\\')) {
                    $packageJobs[] = $job::class;
                }
            }
        }
    }

    expect($packageJobs)->toBe([
        PrepareSurveyRowPaths::class,
        FinishSurveyRowImport::class,
        FinishChoiceListEntryImport::class,
        LinkModuleVersionToLocales::class,
        ImportAllLanguageStrings::class,
        FinishXlsformTemplateImport::class,
    ]);
});
