<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Exports\ChoiceListAsMediaAttachmentExport;
use Stats4sd\FilamentOdkLink\Exports\XlsformExport\XlsformChoicesExport;
use Stats4sd\FilamentOdkLink\Exports\XlsformExport\XlsformSurveyExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

function teamLocaleFor(Locale $defaultLocale): Locale
{
    $localeId = DB::table('locales')->insertGetId([
        'language_id' => $defaultLocale->language_id,
        'is_default' => false,
        'description' => 'Team locale',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Locale::find($localeId);
}

function stringOf(SurveyRow|ChoiceListEntry $row, Locale $locale, string $type, ?string $text): void
{
    $typeId = DB::table('language_string_types')->where('name', $type)->value('id') ?? makeLanguageStringType($type)->id;

    DB::table('language_strings')->insert([
        'locale_id' => $locale->id,
        'language_string_type_id' => $typeId,
        'linked_entry_id' => $row->id,
        'linked_entry_type' => $row::class,
        'text' => $text,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function surveyRowIn(string $name): SurveyRow
{
    $template = makeXlsformTemplate();
    $version = addModuleVersion($template, 'module');

    $id = DB::table('survey_rows')->insertGetId([
        'xlsform_module_version_id' => $version->id,
        'row_number' => 1,
        'name' => $name,
        'type' => 'text',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return SurveyRow::find($id);
}

function surveyExportWithLocales(Locale ...$locales): XlsformSurveyExport
{
    $export = (new ReflectionClass(XlsformSurveyExport::class))->newInstanceWithoutConstructor();
    $export->locales = collect($locales);
    $export->propertyHeadings = collect();

    return $export;
}

function fallbackFormUsing(XlsformTemplate $template, XlsformModuleVersion ...$versions): Xlsform
{
    $teamId = DB::table('teams')->insertGetId(['name' => 'Team', 'created_at' => now(), 'updated_at' => now()]);

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

function fallbackChoicesExport(Xlsform $form, Locale $locale): XlsformChoicesExport
{
    $export = (new ReflectionClass(XlsformChoicesExport::class))->newInstanceWithoutConstructor();
    $export->xlsform = $form;
    $export->locales = collect([$locale]);
    $export->propertyHeadings = collect();

    return $export;
}

beforeEach(function () {
    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('hasTenancy')->andReturn(false);
    $mockPanel->shouldReceive('getTenantModel')->andReturn(null);

    $mockRegistry = Mockery::mock(PanelRegistry::class);
    $mockRegistry->shouldReceive('getDefault')->andReturn($mockPanel);

    app()->instance(PanelRegistry::class, $mockRegistry);

    $this->default = makeLocale('es');
    $this->team = teamLocaleFor($this->default);
});

it('keeps the team locale text when it has some, even if the default locale differs', function () {
    $row = surveyRowIn('q1');
    stringOf($row, $this->default, 'label', 'Hola');
    stringOf($row, $this->team, 'label', 'Che');

    $mapped = surveyExportWithLocales($this->team)->map($row->fresh());

    expect($mapped['label::es (es)'])->toBe('Che');
});

it('uses the default locale text when the team locale has no string', function () {
    $row = surveyRowIn('q1');
    stringOf($row, $this->default, 'label', 'Hola');

    $mapped = surveyExportWithLocales($this->team)->map($row->fresh());

    expect($mapped['label::es (es)'])->toBe('Hola');
});

it('uses the default locale text when the team locale string is empty or blank', function (?string $teamText) {
    $row = surveyRowIn('q1');
    stringOf($row, $this->default, 'label', 'Hola');
    stringOf($row, $this->team, 'label', $teamText);

    $mapped = surveyExportWithLocales($this->team)->map($row->fresh());

    expect($mapped['label::es (es)'])->toBe('Hola');
})->with(['empty' => [''], 'space' => [' '], 'null' => [null]]);

it('leaves the cell empty when neither locale has text, and never takes text from another language', function () {
    $english = makeLocale('en');
    $row = surveyRowIn('q1');
    stringOf($row, $english, 'label', 'Hello');

    $mapped = surveyExportWithLocales($this->team)->map($row->fresh());

    expect($mapped['label::es (es)'])->toBe('');
});

it('falls back for every text string type', function (string $type) {
    $row = surveyRowIn('q1');
    stringOf($row, $this->default, $type, 'Texto');

    $mapped = surveyExportWithLocales($this->team)->map($row->fresh());

    expect($mapped["{$type}::es (es)"])->toBe('Texto');
})->with(['label', 'hint', 'required_message', 'relevant_message', 'constraint_message']);

it('does not fall back for media columns', function () {
    $row = surveyRowIn('q1');
    stringOf($row, $this->default, 'mediaimage', 'picture.png');

    $mapped = surveyExportWithLocales($this->team)->map($row->fresh());

    expect($mapped['media::image::es (es)'])->toBe('');
});

it('leaves a default locale unchanged and finds no other locale for it', function () {
    $row = surveyRowIn('q1');
    stringOf($row, $this->default, 'label', 'Hola');
    stringOf($row, $this->team, 'label', 'Che');

    $mapped = surveyExportWithLocales($this->default)->map($row->fresh());

    expect($mapped['label::es (es)'])->toBe('Hola');

    $emptyRow = surveyRowIn('q2');
    stringOf($emptyRow, $this->team, 'label', 'Che');

    expect(surveyExportWithLocales($this->default)->map($emptyRow->fresh())['label::es (es)'])->toBe('');
});

it('falls back to the default locale label in the choices export, including through same-named duplicates', function () {
    $template = makeXlsformTemplate();
    $firstVersion = addModuleVersion($template, 'first');
    $secondVersion = addModuleVersion($template, 'second');

    $withLabel = addChoiceListEntry(addChoiceList($firstVersion, 'who_family'), 'female_adult');
    $withoutLabel = addChoiceListEntry(addChoiceList($secondVersion, 'who_family'), 'female_adult');

    stringOf($withLabel, $this->default, 'label', 'Mujer adulta');
    stringOf($withoutLabel, $this->team, 'label', '');

    $export = fallbackChoicesExport(fallbackFormUsing($template, $firstVersion, $secondVersion), $this->team);

    $mapped = $export->map($export->query()->get()->first());

    expect($mapped['label::es (es)'])->toBe('Mujer adulta');
});

it('keeps the team locale label in the choices export when it has one', function () {
    $template = makeXlsformTemplate();
    $version = addModuleVersion($template, 'only');

    $entry = addChoiceListEntry(addChoiceList($version, 'who_family'), 'female_adult');
    stringOf($entry, $this->default, 'label', 'Mujer adulta');
    stringOf($entry, $this->team, 'label', 'Mujer');

    $export = fallbackChoicesExport(fallbackFormUsing($template, $version), $this->team);

    $mapped = $export->map($export->query()->get()->first());

    expect($mapped['label::es (es)'])->toBe('Mujer');
});

it('falls back to the default locale label in the choice list attachment export', function () {
    $template = makeXlsformTemplate();
    $version = addModuleVersion($template, 'only');
    $list = addChoiceList($version, 'who_family');
    $entry = addChoiceListEntry($list, 'female_adult');
    stringOf($entry, $this->default, 'label', 'Mujer adulta');

    $export = (new ReflectionClass(ChoiceListAsMediaAttachmentExport::class))->newInstanceWithoutConstructor();
    $export->locales = new EloquentCollection([$this->team]);
    $export->propertyHeadings = collect();
    $export->choiceListEntries = new EloquentCollection([$entry->fresh()]);

    $row = $export->collection()->first();

    expect($row['label::es (es)'])->toBe('Mujer adulta');
});
