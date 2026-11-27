<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Events\AfterImport;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateLanguageStringImport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;

beforeEach(function () {
    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('hasTenancy')->andReturn(false);
    $mockPanel->shouldReceive('getTenantModel')->andReturn(null);
    $mockRegistry = Mockery::mock(PanelRegistry::class);
    $mockRegistry->shouldReceive('getDefault')->andReturn($mockPanel);
    app()->instance(PanelRegistry::class, $mockRegistry);

    makeLocale('en');
    makeLanguageStringType('label');

    $this->template = makeXlsformTemplate();

    // the file import runs the rows of the sheet through model() and upserts what it returns, then cleans up in afterImport().
    $this->importRow = function (string $sheet, array $row) {
        $import = new XlsformTemplateLanguageStringImport($this->template->fresh(), 'label::English (en)', $sheet);

        $models = $import->model($row) ?? [];

        if ($models !== []) {
            LanguageString::query()->upsert(
                collect($models)->map(fn (LanguageString $model) => tap($model)->updateTimestamps()->getAttributes())->all(),
                ['locale_id', 'linked_entry_id', 'linked_entry_type', 'language_string_type_id'],
            );
        }

        $import->afterImport(Mockery::mock(AfterImport::class));

        return $models;
    };
});

it('gives the default string to the entry of every list that has the same list name', function () {
    $firstList = addChoiceList(addModuleVersion($this->template, 'first'), 'yes_no');
    $secondList = addChoiceList(addModuleVersion($this->template, 'second'), 'yes_no');
    $otherList = addChoiceList(addModuleVersion($this->template, 'other'), 'months');

    $firstYes = addChoiceListEntry($firstList, 'yes');
    $secondYes = addChoiceListEntry($secondList, 'yes');
    $otherYes = addChoiceListEntry($otherList, 'yes');

    ($this->importRow)('choices', ['list_name' => 'yes_no', 'name' => 'yes', 'label::English (en)' => 'Yes']);

    expect($firstYes->refresh()->languageStrings->pluck('text')->all())->toBe(['Yes'])
        ->and($secondYes->refresh()->languageStrings->pluck('text')->all())->toBe(['Yes'])
        ->and($otherYes->refresh()->languageStrings)->toHaveCount(0);
});

it('does not give the template default string to an entry owned by a team', function () {
    $firstList = addChoiceList(addModuleVersion($this->template, 'first'), 'crop_list');
    $secondList = addChoiceList(addModuleVersion($this->template, 'second'), 'crop_list');

    $teamId = DB::table('teams')->insertGetId(['name' => 'Team', 'created_at' => now(), 'updated_at' => now()]);

    $globalBean = addChoiceListEntry($firstList, '41');
    $teamBean = addChoiceListEntry($secondList, '41', ['owner_id' => $teamId]);

    ($this->importRow)('choices', ['list_name' => 'crop_list', 'name' => '41', 'label::English (en)' => 'Bean']);

    expect($globalBean->refresh()->languageStrings->pluck('text')->all())->toBe(['Bean'])
        ->and($teamBean->refresh()->languageStrings)->toHaveCount(0);
});

it('keeps writing a survey row string to one row only when the name and type match several rows', function () {
    $firstVersion = addModuleVersion($this->template, 'first');
    $secondVersion = addModuleVersion($this->template, 'second');

    foreach ([$firstVersion, $secondVersion] as $version) {
        DB::table('survey_rows')->insert([
            'xlsform_module_version_id' => $version->id,
            'row_number' => 1,
            'name' => 'intro',
            'type' => 'note',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $models = ($this->importRow)('survey', ['name' => 'intro', 'type' => 'note', 'label::English (en)' => 'Welcome']);

    expect($models)->toHaveCount(1)
        ->and(DB::table('language_strings')->count())->toBe(1);
});

it('keeps the strings of every same-named list when the import cleans up after itself', function () {
    $firstList = addChoiceList(addModuleVersion($this->template, 'first'), 'yes_no');
    $secondList = addChoiceList(addModuleVersion($this->template, 'second'), 'yes_no');

    $firstYes = addChoiceListEntry($firstList, 'yes');
    $secondYes = addChoiceListEntry($secondList, 'yes');

    ($this->importRow)('choices', ['list_name' => 'yes_no', 'name' => 'yes', 'label::English (en)' => 'Yes']);

    DB::table('language_strings')->update(['updated_during_import' => false]);

    ($this->importRow)('choices', ['list_name' => 'yes_no', 'name' => 'yes', 'label::English (en)' => 'Yes again']);

    expect($firstYes->refresh()->languageStrings->pluck('text')->all())->toBe(['Yes again'])
        ->and($secondYes->refresh()->languageStrings->pluck('text')->all())->toBe(['Yes again']);
});
