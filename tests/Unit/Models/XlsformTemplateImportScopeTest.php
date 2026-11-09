<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

beforeEach(function () {
    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('hasTenancy')->andReturn(false);
    $mockPanel->shouldReceive('getTenantModel')->andReturn(null);
    $mockRegistry = Mockery::mock(PanelRegistry::class);
    $mockRegistry->shouldReceive('getDefault')->andReturn($mockPanel);
    app()->instance(PanelRegistry::class, $mockRegistry);

    $this->template = makeXlsformTemplate();
    $this->globalVersion = addModuleVersion($this->template, 'intro');

    $teamId = DB::table('teams')->insertGetId(['name' => 'Team', 'created_at' => now(), 'updated_at' => now()]);

    $this->teamVersionId = DB::table('xlsform_module_versions')->insertGetId([
        'xlsform_module_id' => $this->globalVersion->xlsform_module_id,
        'owner_id' => $teamId,
        'name' => 'intro - Team',
        'is_default' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->teamVersion = XlsformModuleVersion::find($this->teamVersionId);

    $locale = makeLocale();
    $labelType = makeLanguageStringType();

    foreach ([$this->globalVersion->id => 'global', $this->teamVersionId => 'team'] as $versionId => $prefix) {
        $surveyRowId = DB::table('survey_rows')->insertGetId([
            'xlsform_module_version_id' => $versionId,
            'row_number' => 1,
            'name' => "{$prefix}_question",
            'type' => 'text',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $choiceList = addChoiceList(XlsformModuleVersion::find($versionId), "{$prefix}_list");
        $entry = addChoiceListEntry($choiceList, "{$prefix}_entry");

        foreach ([[SurveyRow::class, $surveyRowId], [ChoiceListEntry::class, $entry->id]] as [$type, $id]) {
            DB::table('language_strings')->insert([
                'locale_id' => $locale->id,
                'language_string_type_id' => $labelType->id,
                'linked_entry_type' => $type,
                'linked_entry_id' => $id,
                'text' => "{$prefix} text",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
});

it('limits the template import scope to the global versions', function () {
    expect($this->template->surveyRowsForImport()->pluck('survey_rows.name')->all())->toBe(['global_question'])
        ->and($this->template->choiceListsForImport()->pluck('choice_lists.list_name')->all())->toBe(['global_list'])
        ->and($this->template->choiceListEntriesForImport()->pluck('choice_list_entries.name')->all())->toBe(['global_entry'])
        ->and($this->template->surveyLanguageStringsForImport()->pluck('language_strings.text')->all())->toBe(['global text'])
        ->and($this->template->choiceListEntryLanguageStringsForImport()->pluck('language_strings.text')->all())->toBe(['global text']);
});

it('still reaches the team versions through the plain template relations', function () {
    expect($this->template->surveyRows()->pluck('survey_rows.name')->all())->toContain('global_question', 'team_question')
        ->and($this->template->choiceListEntries()->pluck('choice_list_entries.name')->all())->toContain('global_entry', 'team_entry');
});

it('uses the whole version as the import scope of a module version', function () {
    expect($this->teamVersion->surveyRowsForImport()->pluck('name')->all())->toBe(['team_question'])
        ->and($this->teamVersion->choiceListsForImport()->pluck('list_name')->all())->toBe(['team_list'])
        ->and($this->teamVersion->choiceListEntriesForImport()->pluck('choice_list_entries.name')->all())->toBe(['team_entry'])
        ->and($this->teamVersion->surveyLanguageStringsForImport()->pluck('language_strings.text')->all())->toBe(['team text'])
        ->and($this->teamVersion->choiceListEntryLanguageStringsForImport()->pluck('language_strings.text')->all())->toBe(['team text']);
});
