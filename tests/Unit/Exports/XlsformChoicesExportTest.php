<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Exports\XlsformExport\XlsformChoicesExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

beforeEach(function () {
    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('hasTenancy')->andReturn(false);
    $mockPanel->shouldReceive('getTenantModel')->andReturn(null);

    $mockRegistry = Mockery::mock(PanelRegistry::class);
    $mockRegistry->shouldReceive('getDefault')->andReturn($mockPanel);

    app()->instance(PanelRegistry::class, $mockRegistry);

    $this->teamId = DB::table('teams')->insertGetId(['name' => 'Team', 'created_at' => now(), 'updated_at' => now()]);
});

function makeFormUsing(XlsformTemplate $template, int $teamId, XlsformModuleVersion ...$versions): Xlsform
{
    $xlsformId = DB::table('xlsforms')->insertGetId([
        'xlsform_template_id' => $template->id,
        'owner_id' => $teamId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ($versions as $order => $version) {
        DB::table('selected_xlsform_module_versions')->insert([
            'xlsform_id' => $xlsformId,
            'xlsform_module_version_id' => $version->id,
            'order' => $order + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return Xlsform::find($xlsformId);
}

// The constructor reads MySQL-only json_keys(), so build the export without it; query() only needs the form.
function choicesExportFor(Xlsform $xlsform): XlsformChoicesExport
{
    $export = (new ReflectionClass(XlsformChoicesExport::class))->newInstanceWithoutConstructor();
    $export->xlsform = $xlsform;

    return $export;
}

function labelEntry(ChoiceListEntry $entry, string $text): void
{
    $locale = DB::table('locales')->first() ?? makeLocale();
    $labelTypeId = DB::table('language_string_types')->where('name', 'label')->value('id') ?? makeLanguageStringType('label')->id;

    DB::table('language_strings')->insert([
        'locale_id' => $locale->id,
        'language_string_type_id' => $labelTypeId,
        'linked_entry_id' => $entry->id,
        'linked_entry_type' => ChoiceListEntry::class,
        'text' => $text,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('exports the labelled entry when same-named lists hold the same entry and only the lower-id one has a label', function () {
    $template = makeXlsformTemplate();
    $intensificationVersion = addModuleVersion($template, 'crop_intensification');
    $cropsVersion = addModuleVersion($template, 'crops');

    $intensificationList = addChoiceList($intensificationVersion, 'crop_list');
    $cropsList = addChoiceList($cropsVersion, 'crop_list');

    $labelled = addChoiceListEntry($intensificationList, '1');
    $unlabelled = addChoiceListEntry($cropsList, '1');

    labelEntry($labelled, 'Rice');

    $form = makeFormUsing($template, $this->teamId, $intensificationVersion, $cropsVersion);

    $rows = choicesExportFor($form)->query()->get();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->id)->toBe($labelled->id);
    expect($unlabelled->id)->toBeGreaterThan($labelled->id);
});

it('still exports an entry that has no label in any list', function () {
    $template = makeXlsformTemplate();
    $firstVersion = addModuleVersion($template, 'first');
    $secondVersion = addModuleVersion($template, 'second');

    addChoiceListEntry(addChoiceList($firstVersion, 'crop_list'), '1');
    $last = addChoiceListEntry(addChoiceList($secondVersion, 'crop_list'), '1');

    $form = makeFormUsing($template, $this->teamId, $firstVersion, $secondVersion);

    $rows = choicesExportFor($form)->query()->get();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->id)->toBe($last->id);
});

it('exports choices in template order, with entries added later by the team last', function () {
    $template = makeXlsformTemplate();
    $version = addModuleVersion($template, 'metadata_close');

    $rapport = addChoiceList($version, 'rapport');
    foreach (['easy', 'medium', 'difficult', 'very_difficult', 'no_answer'] as $name) {
        addChoiceListEntry($rapport, $name);
    }

    $reliability = addChoiceList($version, 'reliability');
    foreach (['5', '4', '3', '2', '1'] as $name) {
        addChoiceListEntry($reliability, $name);
    }

    addChoiceListEntry($rapport, 'team_added', ['owner_id' => $this->teamId]);

    $form = makeFormUsing($template, $this->teamId, $version);

    $rows = choicesExportFor($form)->query()->get();

    expect($rows->where('list_name', 'rapport')->pluck('name')->values()->all())
        ->toBe(['easy', 'medium', 'difficult', 'very_difficult', 'no_answer', 'team_added']);
    expect($rows->where('list_name', 'reliability')->pluck('name')->values()->all())
        ->toBe(['5', '4', '3', '2', '1']);
    expect($rows->pluck('list_name')->unique()->values()->all())->toBe(['rapport', 'reliability']);
});

it('exports team entries after the global ones even when the global entries were recreated later', function () {
    $template = makeXlsformTemplate();
    $version = addModuleVersion($template, 'crops');
    $list = addChoiceList($version, 'crop_list');

    addChoiceListEntry($list, '41', ['owner_id' => $this->teamId]);
    addChoiceListEntry($list, '42', ['owner_id' => $this->teamId]);

    foreach (['1', '2', '3', '97'] as $name) {
        addChoiceListEntry($list, $name);
    }

    $form = makeFormUsing($template, $this->teamId, $version);

    $rows = choicesExportFor($form)->query()->get();

    expect($rows->pluck('name')->values()->all())->toBe(['1', '2', '3', '97', '41', '42']);
});
