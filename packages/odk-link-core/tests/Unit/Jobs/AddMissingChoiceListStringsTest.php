<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Jobs\AddMissingChoiceListStrings;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;

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
