<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Jobs\AddMissingChoiceListStrings;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;

beforeEach(function () {
    // Model hooks that fire on save/read need Filament panel registry to resolve.
    // Stub the registry with a mock panel that has no tenancy.
    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('hasTenancy')->andReturn(false);
    $mockPanel->shouldReceive('getTenantModel')->andReturn(null);
    $mockRegistry = Mockery::mock(PanelRegistry::class);
    $mockRegistry->shouldReceive('getDefault')->andReturn($mockPanel);
    app()->instance(PanelRegistry::class, $mockRegistry);
});

it('skips an entry with no matching translated counterpart instead of crashing', function () {
    $template = makeXlsformTemplate();
    $translatedVersion = addModuleVersion($template, 'source');
    $targetVersion = addModuleVersion($template, 'target');

    $translatedList = addChoiceList($translatedVersion, 'yes_no');
    $targetList = addChoiceList($targetVersion, 'yes_no');

    $translatedYes = addChoiceListEntry($translatedList, 'yes');
    addChoiceListEntry($targetList, 'yes');
    $targetMaybe = addChoiceListEntry($targetList, 'maybe');

    $locale = makeLocale();
    $labelType = makeLanguageStringType('label');

    DB::table('language_strings')->insert([
        'locale_id' => $locale->id,
        'language_string_type_id' => $labelType->id,
        'linked_entry_id' => $translatedYes->id,
        'linked_entry_type' => ChoiceListEntry::class,
        'text' => 'Yes',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new AddMissingChoiceListStrings($template))->handle();

    $targetYes = ChoiceListEntry::where('choice_list_id', $targetList->id)->where('name', 'yes')->first();

    expect($targetYes->languageStrings)->toHaveCount(1);
    expect($targetYes->languageStrings->first()->text)->toBe('Yes');
    expect($targetMaybe->refresh()->languageStrings)->toHaveCount(0);
});

it('backfills remaining entries of a partially translated choice list without duplicating existing strings', function () {
    $template = makeXlsformTemplate();
    $translatedVersion = addModuleVersion($template, 'source');
    $targetVersion = addModuleVersion($template, 'target');

    $translatedList = addChoiceList($translatedVersion, 'yes_no');
    $targetList = addChoiceList($targetVersion, 'yes_no');

    $translatedYes = addChoiceListEntry($translatedList, 'yes');
    $translatedNo = addChoiceListEntry($translatedList, 'no');
    $targetYes = addChoiceListEntry($targetList, 'yes');
    $targetNo = addChoiceListEntry($targetList, 'no');

    $locale = makeLocale();
    $labelType = makeLanguageStringType('label');

    foreach ([[$translatedYes, 'Yes'], [$translatedNo, 'No']] as [$entry, $text]) {
        DB::table('language_strings')->insert([
            'locale_id' => $locale->id,
            'language_string_type_id' => $labelType->id,
            'linked_entry_id' => $entry->id,
            'linked_entry_type' => ChoiceListEntry::class,
            'text' => $text,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Simulates a previous run of the job that backfilled `yes` before failing partway through the loop.
    DB::table('language_strings')->insert([
        'locale_id' => $locale->id,
        'language_string_type_id' => $labelType->id,
        'linked_entry_id' => $targetYes->id,
        'linked_entry_type' => ChoiceListEntry::class,
        'text' => 'Yes',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new AddMissingChoiceListStrings($template))->handle();

    expect($targetYes->refresh()->languageStrings)->toHaveCount(1);
    expect($targetNo->refresh()->languageStrings)->toHaveCount(1);
    expect($targetNo->refresh()->languageStrings->first()->text)->toBe('No');
});

function addLabel(ChoiceListEntry $entry, $locale, $labelType, string $text): void
{
    DB::table('language_strings')->insert([
        'locale_id' => $locale->id,
        'language_string_type_id' => $labelType->id,
        'linked_entry_id' => $entry->id,
        'linked_entry_type' => ChoiceListEntry::class,
        'text' => $text,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('does not use the list being filled as its own source when it only holds labelled team entries', function () {
    $template = makeXlsformTemplate();
    $cropsVersion = addModuleVersion($template, 'crops');
    $intensificationVersion = addModuleVersion($template, 'crop_intensification');

    $cropsList = addChoiceList($cropsVersion, 'crop_list');
    $intensificationList = addChoiceList($intensificationVersion, 'crop_list');

    $teamId = DB::table('teams')->insertGetId(['name' => 'Team', 'created_at' => now(), 'updated_at' => now()]);

    $locale = makeLocale();
    $labelType = makeLanguageStringType('label');

    $cropsGlobalRice = addChoiceListEntry($cropsList, '1');
    $cropsTeamBean = addChoiceListEntry($cropsList, '41', ['owner_id' => $teamId]);
    $intensificationGlobalRice = addChoiceListEntry($intensificationList, '1');
    $intensificationTeamBean = addChoiceListEntry($intensificationList, '41', ['owner_id' => $teamId]);

    addLabel($cropsTeamBean, $locale, $labelType, 'Bean');
    addLabel($intensificationGlobalRice, $locale, $labelType, 'Rice');
    addLabel($intensificationTeamBean, $locale, $labelType, 'Bean');

    (new AddMissingChoiceListStrings($template))->handle();

    expect($cropsGlobalRice->refresh()->languageStrings)->toHaveCount(1);
    expect($cropsGlobalRice->languageStrings->first()->text)->toBe('Rice');
});

it('does not copy a label from a team entry owned by a different team', function () {
    $template = makeXlsformTemplate();
    $sourceVersion = addModuleVersion($template, 'source');
    $targetVersion = addModuleVersion($template, 'target');

    $sourceList = addChoiceList($sourceVersion, 'crop_list');
    $targetList = addChoiceList($targetVersion, 'crop_list');

    $teamOneId = DB::table('teams')->insertGetId(['name' => 'Team 1', 'created_at' => now(), 'updated_at' => now()]);
    $teamTwoId = DB::table('teams')->insertGetId(['name' => 'Team 2', 'created_at' => now(), 'updated_at' => now()]);

    $locale = makeLocale();
    $labelType = makeLanguageStringType('label');

    $sourceTeamTwoBean = addChoiceListEntry($sourceList, '41', ['owner_id' => $teamTwoId]);
    $targetTeamOneBean = addChoiceListEntry($targetList, '41', ['owner_id' => $teamOneId]);

    addLabel($sourceTeamTwoBean, $locale, $labelType, 'Frijol de team two');

    (new AddMissingChoiceListStrings($template))->handle();

    expect($targetTeamOneBean->refresh()->languageStrings)->toHaveCount(0);
});

function makeTeamLocale(string $isoAlpha2 = 'es'): Locale
{
    $languageId = DB::table('languages')->insertGetId([
        'iso_alpha2' => $isoAlpha2,
        'name' => $isoAlpha2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $localeId = DB::table('locales')->insertGetId([
        'language_id' => $languageId,
        'is_default' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Locale::find($localeId);
}

function addString(ChoiceListEntry $entry, Locale $locale, string $text, int $typeId): void
{
    DB::table('language_strings')->insert([
        'locale_id' => $locale->id,
        'language_string_type_id' => $typeId,
        'linked_entry_id' => $entry->id,
        'linked_entry_type' => ChoiceListEntry::class,
        'text' => $text,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function stringsOf(ChoiceListEntry $entry): array
{
    return $entry->refresh()->languageStrings
        ->mapWithKeys(fn ($languageString) => [$languageString->locale_id => $languageString->text])
        ->all();
}

it('gives default strings to a global entry that only kept its team strings, without touching the team strings', function () {
    $template = makeXlsformTemplate();
    $donorList = addChoiceList(addModuleVersion($template, 'donor'), 'yes_no');
    $brokenList = addChoiceList(addModuleVersion($template, 'broken'), 'yes_no');

    $donorYes = addChoiceListEntry($donorList, 'yes');
    $brokenYes = addChoiceListEntry($brokenList, 'yes');

    $defaultLocale = makeLocale('en');
    $teamLocale = makeTeamLocale('es');
    $labelType = makeLanguageStringType('label');

    addString($donorYes, $defaultLocale, 'Yes', $labelType->id);
    addString($donorYes, $teamLocale, 'Si (donor team text)', $labelType->id);
    addString($brokenYes, $teamLocale, 'Si (own team text)', $labelType->id);

    (new AddMissingChoiceListStrings($template))->handle();

    expect(stringsOf($brokenYes))->toBe([
        $teamLocale->id => 'Si (own team text)',
        $defaultLocale->id => 'Yes',
    ]);
});

it('takes the default strings from a list that has them even when another same-named list only has team strings', function () {
    $template = makeXlsformTemplate();
    $teamOnlyList = addChoiceList(addModuleVersion($template, 'first'), 'yes_no');
    $brokenList = addChoiceList(addModuleVersion($template, 'second'), 'yes_no');
    $donorList = addChoiceList(addModuleVersion($template, 'third'), 'yes_no');

    $teamOnlyYes = addChoiceListEntry($teamOnlyList, 'yes');
    $brokenYes = addChoiceListEntry($brokenList, 'yes');
    $donorYes = addChoiceListEntry($donorList, 'yes');

    $defaultLocale = makeLocale('en');
    $teamLocale = makeTeamLocale('es');
    $labelType = makeLanguageStringType('label');

    addString($teamOnlyYes, $teamLocale, 'Si 1', $labelType->id);
    addString($brokenYes, $teamLocale, 'Si 2', $labelType->id);
    addString($donorYes, $defaultLocale, 'Yes', $labelType->id);

    (new AddMissingChoiceListStrings($template))->handle();

    expect(stringsOf($brokenYes)[$defaultLocale->id] ?? null)->toBe('Yes')
        ->and(stringsOf($teamOnlyYes)[$defaultLocale->id] ?? null)->toBe('Yes');
});

it('leaves entries owned by a team alone when they only hold team strings', function () {
    $template = makeXlsformTemplate();
    $donorList = addChoiceList(addModuleVersion($template, 'donor'), 'crop_list');
    $targetList = addChoiceList(addModuleVersion($template, 'target'), 'crop_list');

    $teamId = DB::table('teams')->insertGetId(['name' => 'Team', 'created_at' => now(), 'updated_at' => now()]);

    $donorBean = addChoiceListEntry($donorList, '41', ['owner_id' => $teamId]);
    $targetBean = addChoiceListEntry($targetList, '41', ['owner_id' => $teamId]);

    $defaultLocale = makeLocale('en');
    $teamLocale = makeTeamLocale('es');
    $labelType = makeLanguageStringType('label');

    addString($donorBean, $defaultLocale, 'Bean', $labelType->id);
    addString($targetBean, $teamLocale, 'Frijol', $labelType->id);

    (new AddMissingChoiceListStrings($template))->handle();

    expect(stringsOf($targetBean))->toBe([$teamLocale->id => 'Frijol']);
});

it('does not repair lists of versions that are not default versions', function () {
    $template = makeXlsformTemplate();
    $donorList = addChoiceList(addModuleVersion($template, 'donor'), 'yes_no');

    $cloneVersion = addModuleVersion($template, 'clone');
    DB::table('xlsform_module_versions')->where('id', $cloneVersion->id)->update(['is_default' => false]);
    $cloneList = addChoiceList($cloneVersion, 'yes_no');

    $donorYes = addChoiceListEntry($donorList, 'yes');
    $cloneYes = addChoiceListEntry($cloneList, 'yes');

    $defaultLocale = makeLocale('en');
    $teamLocale = makeTeamLocale('es');
    $labelType = makeLanguageStringType('label');

    addString($donorYes, $defaultLocale, 'Yes', $labelType->id);
    addString($cloneYes, $teamLocale, 'Si', $labelType->id);

    (new AddMissingChoiceListStrings($template))->handle();

    expect(stringsOf($cloneYes))->toBe([$teamLocale->id => 'Si']);
});

it('changes nothing when it runs a second time', function () {
    $template = makeXlsformTemplate();
    $donorList = addChoiceList(addModuleVersion($template, 'donor'), 'yes_no');
    $brokenList = addChoiceList(addModuleVersion($template, 'broken'), 'yes_no');

    $donorYes = addChoiceListEntry($donorList, 'yes');
    $brokenYes = addChoiceListEntry($brokenList, 'yes');

    $defaultLocale = makeLocale('en');
    $teamLocale = makeTeamLocale('es');
    $labelType = makeLanguageStringType('label');

    addString($donorYes, $defaultLocale, 'Yes', $labelType->id);
    addString($brokenYes, $teamLocale, 'Si', $labelType->id);

    (new AddMissingChoiceListStrings($template))->handle();

    $afterFirstRun = DB::table('language_strings')->count();

    (new AddMissingChoiceListStrings($template))->handle();

    expect(DB::table('language_strings')->count())->toBe($afterFirstRun);
});
