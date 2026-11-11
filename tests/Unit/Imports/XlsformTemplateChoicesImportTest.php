<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Events\AfterImport;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateChoicesImport;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateWorkbookImport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;

beforeEach(function () {
    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('hasTenancy')->andReturn(false);
    $mockPanel->shouldReceive('getTenantModel')->andReturn(null);
    $mockRegistry = Mockery::mock(PanelRegistry::class);
    $mockRegistry->shouldReceive('getDefault')->andReturn($mockPanel);
    app()->instance(PanelRegistry::class, $mockRegistry);

    $this->template = makeXlsformTemplate();
    $this->version = addModuleVersion($this->template, 'demographics');
    $this->list = addChoiceList($this->version, 'yn');

    // runs the choices sheet import for the given rows, like the queued import does for a chunk.
    $this->importChoices = function (array $rows) {
        $import = new XlsformTemplateChoicesImport($this->version->fresh(), collect(['label::English (en)']));
        $import->collection(collect($rows)->map(fn (array $row) => collect($row)));
    };

    // like the start of a Replace: nothing counts as updated until the import touches it.
    $this->resetUpdatedFlags = fn () => DB::table('choice_list_entries')->update(['updated_during_import' => false]);

    $this->runCleanup = fn () => (new XlsformTemplateWorkbookImport($this->template->fresh(), collect()))
        ->afterImport(Mockery::mock(AfterImport::class));

    // a team locale string linked to an entry (the file import never touches these).
    $this->addTeamString = function (ChoiceListEntry $entry, string $text) {
        $this->teamLocale ??= makeLocale('es');
        $this->labelType ??= makeLanguageStringType('label');

        return LanguageString::create([
            'locale_id' => $this->teamLocale->id,
            'language_string_type_id' => $this->labelType->id,
            'linked_entry_type' => ChoiceListEntry::class,
            'linked_entry_id' => $entry->id,
            'text' => $text,
        ]);
    };

    $this->entryIds = fn () => ChoiceListEntry::withoutGlobalScopes()
        ->whereNull('owner_id')
        ->orderBy('name')->orderBy('cascade_filter')
        ->get()
        ->mapWithKeys(fn ($e) => [$e->name . '|' . ($e->cascade_filter ?? '') => $e->id])
        ->all();
});

it('keeps the ids of all global entries when the same choices are imported twice', function () {
    $rows = [
        ['list_name' => 'yn', 'name' => 'yes', 'label::English (en)' => 'Yes'],
        ['list_name' => 'yn', 'name' => 'no', 'label::English (en)' => 'No'],
    ];

    ($this->importChoices)($rows);
    $before = ($this->entryIds)();
    expect($before)->toHaveCount(2);

    ($this->resetUpdatedFlags)();
    ($this->importChoices)($rows);

    expect(($this->entryIds)())->toBe($before)
        ->and(ChoiceListEntry::withoutGlobalScopes()->where('updated_during_import', true)->count())->toBe(2);
});

it('keeps a team locale language string linked to the same entry after a second import and cleanup', function () {
    $rows = [['list_name' => 'yn', 'name' => 'yes', 'label::English (en)' => 'Yes']];

    ($this->importChoices)($rows);
    $entry = ChoiceListEntry::withoutGlobalScopes()->firstWhere('name', 'yes');
    $string = ($this->addTeamString)($entry, 'Sí');

    ($this->resetUpdatedFlags)();
    ($this->importChoices)($rows);
    ($this->runCleanup)();

    $string->refresh();
    expect($string->text)->toBe('Sí')
        ->and($string->linked_entry_id)->toBe($entry->id)
        ->and(ChoiceListEntry::withoutGlobalScopes()->whereKey($entry->id)->exists())->toBeTrue();
});

it('deletes an entry removed from the file together with its language strings, and inserts a new one', function () {
    ($this->importChoices)([
        ['list_name' => 'yn', 'name' => 'yes'],
        ['list_name' => 'yn', 'name' => 'no'],
    ]);
    $kept = ChoiceListEntry::withoutGlobalScopes()->firstWhere('name', 'yes');
    $removed = ChoiceListEntry::withoutGlobalScopes()->firstWhere('name', 'no');
    ($this->addTeamString)($kept, 'Sí');
    ($this->addTeamString)($removed, 'No');

    ($this->resetUpdatedFlags)();
    ($this->importChoices)([
        ['list_name' => 'yn', 'name' => 'yes'],
        ['list_name' => 'yn', 'name' => 'maybe'],
    ]);
    ($this->runCleanup)();

    $names = ChoiceListEntry::withoutGlobalScopes()->pluck('id', 'name');
    expect($names->keys()->sort()->values()->all())->toBe(['maybe', 'yes'])
        ->and($names['yes'])->toBe($kept->id)
        ->and($names['maybe'])->not->toBe($removed->id)
        ->and(LanguageString::where('linked_entry_id', $removed->id)->count())->toBe(0)
        ->and(LanguageString::where('linked_entry_id', $kept->id)->count())->toBe(1);
});

it('keeps the ids of entries with a cascade filter, including the same name with different filters', function () {
    $rows = [
        ['list_name' => 'yn', 'name' => 'a', 'filter' => 'north'],
        ['list_name' => 'yn', 'name' => 'a', 'filter' => 'south'],
        ['list_name' => 'yn', 'name' => 'a'],
    ];

    ($this->importChoices)($rows);
    $before = ($this->entryIds)();
    expect($before)->toHaveCount(3);

    ($this->resetUpdatedFlags)();
    ($this->importChoices)($rows);

    expect(($this->entryIds)())->toBe($before);
});

it('does not match or change a team-owned entry with the same name in the same list', function () {
    $teamId = DB::table('teams')->insertGetId(['name' => 'Team', 'created_at' => now(), 'updated_at' => now()]);
    $owned = addChoiceListEntry($this->list, 'yes', [
        'owner_id' => $teamId,
        'cascade_filter' => 'team',
        'properties' => json_encode(['custom' => 'mine']),
    ]);

    ($this->importChoices)([['list_name' => 'yn', 'name' => 'yes', 'custom' => 'file']]);
    ($this->resetUpdatedFlags)();
    ($this->importChoices)([['list_name' => 'yn', 'name' => 'yes', 'custom' => 'file']]);

    $owned->refresh();
    expect($owned->properties->all())->toBe(['custom' => 'mine'])
        ->and($owned->updated_during_import)->toBeFalse()
        ->and(ChoiceListEntry::withoutGlobalScopes()->whereNull('owner_id')->count())->toBe(1);
});

it('updates the properties of an existing entry from the new file', function () {
    ($this->importChoices)([['list_name' => 'yn', 'name' => 'yes', 'image' => 'old.png']]);
    $id = ChoiceListEntry::withoutGlobalScopes()->firstWhere('name', 'yes')->id;

    ($this->resetUpdatedFlags)();
    ($this->importChoices)([['list_name' => 'yn', 'name' => 'yes', 'image' => 'new.png']]);

    $entry = ChoiceListEntry::withoutGlobalScopes()->firstWhere('name', 'yes');
    expect($entry->id)->toBe($id)
        ->and($entry->properties->all())->toBe(['image' => 'new.png'])
        ->and($entry->updated_during_import)->toBeTrue();
});
