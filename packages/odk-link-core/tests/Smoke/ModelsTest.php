<?php

use Stats4sd\OdkLink\Core\Models\OdkLink\AppUser;
use Stats4sd\OdkLink\Core\Models\OdkLink\ChoiceList;
use Stats4sd\OdkLink\Core\Models\OdkLink\ChoiceListEntry;
use Stats4sd\OdkLink\Core\Models\OdkLink\Dataset;
use Stats4sd\OdkLink\Core\Models\OdkLink\DatasetVariable;
use Stats4sd\OdkLink\Core\Models\OdkLink\Entity;
use Stats4sd\OdkLink\Core\Models\OdkLink\EntityValue;
use Stats4sd\OdkLink\Core\Models\OdkLink\LanguageString;
use Stats4sd\OdkLink\Core\Models\OdkLink\OdkProject;
use Stats4sd\OdkLink\Core\Models\OdkLink\Platform;
use Stats4sd\OdkLink\Core\Models\OdkLink\RequiredMedia;
use Stats4sd\OdkLink\Core\Models\OdkLink\Submission;
use Stats4sd\OdkLink\Core\Models\OdkLink\SurveyRow;
use Stats4sd\OdkLink\Core\Models\OdkLink\Xlsform;
use Stats4sd\OdkLink\Core\Models\OdkLink\XlsformLanguages\Language;
use Stats4sd\OdkLink\Core\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\OdkLink\Core\Models\OdkLink\XlsformModule;
use Stats4sd\OdkLink\Core\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\OdkLink\Core\Models\OdkLink\XlsformTemplate;
use Stats4sd\OdkLink\Core\Tests\Models\Team;

// Every concrete model should construct and report a table that actually exists.
// This also underpins HelperService::getModelByTablename(), which news up models
// and compares getTable().
it('instantiates each model and resolves an existing table', function (string $model) {
    $instance = new $model;

    expect($instance)->toBeInstanceOf($model)
        ->and(Schema::hasTable($instance->getTable()))->toBeTrue();
})->with([
    AppUser::class,
    ChoiceList::class,
    ChoiceListEntry::class,
    Dataset::class,
    DatasetVariable::class,
    Entity::class,
    EntityValue::class,
    LanguageString::class,
    Language::class,
    Locale::class,
    OdkProject::class,
    Platform::class,
    RequiredMedia::class,
    Submission::class,
    SurveyRow::class,
    Xlsform::class,
    XlsformModule::class,
    XlsformModuleVersion::class,
    XlsformTemplate::class,
]);

it('persists a team via its factory', function () {
    $team = Team::factory()->create();

    expect($team->exists)->toBeTrue()
        ->and($team->name)->not->toBeEmpty()
        ->and(Team::query()->count())->toBe(1);
});
