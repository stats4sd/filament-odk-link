<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Stats4sd\FilamentOdkLink\Jobs\ImportAllLanguageStrings;
use Stats4sd\FilamentOdkLink\Services\XlsformTranslationHelper;

beforeEach(function () {
    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('hasTenancy')->andReturn(false);
    $mockPanel->shouldReceive('getTenantModel')->andReturn(null);
    $mockRegistry = Mockery::mock(PanelRegistry::class);
    $mockRegistry->shouldReceive('getDefault')->andReturn($mockPanel);
    app()->instance(PanelRegistry::class, $mockRegistry);

    Storage::fake('local');

    makeLocale('en');
    $labelType = makeLanguageStringType('label');

    $this->template = makeXlsformTemplate();
    $this->firstYes = addChoiceListEntry(addChoiceList(addModuleVersion($this->template, 'first'), 'yes_no'), 'yes');
    $this->secondYes = addChoiceListEntry(addChoiceList(addModuleVersion($this->template, 'second'), 'yes_no'), 'yes');
    $this->secondNo = addChoiceListEntry($this->secondYes->choiceList, 'no');

    // the second list already holds a team's own translations, like the lists that lost their default strings on staging.
    $teamLanguageId = DB::table('languages')->insertGetId(['iso_alpha2' => 'es', 'name' => 'es', 'created_at' => now(), 'updated_at' => now()]);
    $teamLocaleId = DB::table('locales')->insertGetId(['language_id' => $teamLanguageId, 'is_default' => false, 'created_at' => now(), 'updated_at' => now()]);

    foreach ([[$this->secondYes, 'Si (team)'], [$this->secondNo, 'No (team)']] as [$entry, $text]) {
        DB::table('language_strings')->insert([
            'locale_id' => $teamLocaleId,
            'language_string_type_id' => $labelType->id,
            'linked_entry_id' => $entry->id,
            'linked_entry_type' => $entry::class,
            'text' => $text,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // the same workbook layout the template import reads: one survey sheet and one choices sheet with a label column.
    $this->importWorkbook = function (array $choiceRows) {
        $spreadsheet = new Spreadsheet;

        $spreadsheet->getActiveSheet()->setTitle('survey')->fromArray([
            ['type', 'name', 'label::English (en)'],
            ['text', 'q1', 'Question one'],
        ]);

        $spreadsheet->createSheet()->setTitle('choices')->fromArray([
            ['list_name', 'name', 'label::English (en)'],
            ...$choiceRows,
        ]);

        $path = Storage::disk('local')->path('translations.xlsx');

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        $headings = (new XlsformTranslationHelper)->getTranslatableColumnsFromFile($path);

        (new ImportAllLanguageStrings($path, $this->template->fresh(), $headings))->handle();
    };

    $this->textsOf = fn ($entry) => $entry->refresh()->languageStrings->pluck('text')->all();
});

it('gives the default string to every same-named list when a real workbook is imported', function () {
    ($this->importWorkbook)([
        ['yes_no', 'yes', 'Yes'],
        ['yes_no', 'no', 'No'],
    ]);

    expect(($this->textsOf)($this->firstYes))->toBe(['Yes'])
        ->and(($this->textsOf)($this->secondYes))->toContain('Yes', 'Si (team)')
        ->and(($this->textsOf)($this->secondNo))->toContain('No', 'No (team)');
});

it('keeps the strings of every same-named list when the same workbook is imported again', function () {
    $rows = [
        ['yes_no', 'yes', 'Yes'],
        ['yes_no', 'no', 'No'],
    ];

    ($this->importWorkbook)($rows);
    ($this->importWorkbook)($rows);

    expect(($this->textsOf)($this->firstYes))->toBe(['Yes'])
        ->and(($this->textsOf)($this->secondYes))->toContain('Yes', 'Si (team)')
        ->and(DB::table('language_strings')->count())->toBe(5);
});

it('updates the string of every same-named list when the text changes in a later import', function () {
    ($this->importWorkbook)([
        ['yes_no', 'yes', 'Yes'],
        ['yes_no', 'no', 'No'],
    ]);

    ($this->importWorkbook)([
        ['yes_no', 'yes', 'Yes!'],
        ['yes_no', 'no', 'No'],
    ]);

    expect(($this->textsOf)($this->firstYes))->toBe(['Yes!'])
        ->and(($this->textsOf)($this->secondYes))->toContain('Yes!', 'Si (team)')
        ->and(($this->textsOf)($this->secondYes))->not->toContain('Yes');
});
