# Fix B11 — `AddMissingChoiceListStrings` null-deref + non-idempotent backfill

> **For agentic workers:** Steps use checkbox (`- [ ]`) syntax for tracking.

**Status:** Completed — see [change log](../change-logs/2026-07-23-fix-b11-choice-list-strings-job.md)

**Goal:** Make [AddMissingChoiceListStrings.php](../../src/Jobs/AddMissingChoiceListStrings.php) tolerate a choice list entry with no translated counterpart, and make the whole job safely re-runnable so a partial failure doesn't crash forever or lose entries.

**Architecture:** Two independent defects in the same 40-line `each()` block, fixed together since the second fix's test setup depends on the first:

1. Null-deref — `$matchingEntry` from a `->first()` can be `null`; skip that entry instead of dereferencing it.
2. Non-idempotent backfill — the outer query selects a `ChoiceList` only when **none** of its entries have language strings yet (`whereDoesntHave('choiceListEntries.languageStrings')`). Once any entry in that list gets strings, the whole list becomes invisible to future runs — a mid-loop crash (e.g. the null-deref above) permanently strands every entry after the one that failed. Fix: select choice lists with **at least one** entry still missing strings (`whereHas('choiceListEntries', fn ($q) => $q->whereDoesntHave('languageStrings'))`), and skip already-populated entries inside the loop with `updateOrCreate` (matching the existing idiom in [HasLanguageStrings.php:31](../../src/Models/OdkLink/Traits/HasLanguageStrings.php#L31)) instead of a raw `insert()`.

**Tech Stack:** PHP 8.4 / Laravel 13 / Pest. No new dependencies.

## Global Constraints

- Follow `.claude/laravel-php-guidelines.md` (already loaded via CLAUDE.md) — no comments describing _what_ the code does, `! $x` not `!$x`... match existing style in the file.
- Every new DB-backed test fixture goes through `DB::table(...)->insert()` / `insertGetId()`, not `Model::create()` — this codebase's model `booted()` hooks fire ODK network calls and cross-table cascades on save, so tests insert at the DB layer and re-read the model (see the doc-comment at the top of [tests/Pest.php](../../tests/Pest.php)).
- Don't touch the `matchingList` resolution logic (the `$relationship` / `whereHas` block) — it is correct and out of scope for B11.

---

### Task 1: Add test fixture helpers for locales / language string types / choice list entries

**Files:**

- Modify: `tests/Pest.php`

**Interfaces:**

- Produces: `makeLocale(string $isoAlpha2 = 'en'): Locale`, `makeLanguageStringType(string $name = 'label'): LanguageStringType`, `addChoiceListEntry(ChoiceList $choiceList, string $name, array $attrs = []): ChoiceListEntry` — global functions, usable from any test file without an import (same pattern as the existing `makeXlsformTemplate`/`addModuleVersion`/`addChoiceList`).
- Consumes: existing `addChoiceList(XlsformModuleVersion $version, string $listName, array $attrs = []): ChoiceList` at [tests/Pest.php:62](../../tests/Pest.php#L62).

- [x] **Step 1: Add the three helpers to `tests/Pest.php`**

Add these imports at the top of the file, alongside the existing ones:

```php
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
```

Append these functions after `addChoiceList()`:

```php
function addChoiceListEntry(ChoiceList $choiceList, string $name, array $attrs = []): ChoiceListEntry
{
    $id = DB::table('choice_list_entries')->insertGetId([
        'choice_list_id' => $choiceList->id,
        'name' => $name,
        'properties' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
        ...$attrs,
    ]);

    return ChoiceListEntry::find($id);
}

function makeLocale(string $isoAlpha2 = 'en'): Locale
{
    $languageId = DB::table('languages')->insertGetId([
        'iso_alpha2' => $isoAlpha2,
        'name' => $isoAlpha2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $localeId = DB::table('locales')->insertGetId([
        'language_id' => $languageId,
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Locale::find($localeId);
}

function makeLanguageStringType(string $name = 'label'): LanguageStringType
{
    $id = DB::table('language_string_types')->insertGetId([
        'name' => $name,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return LanguageStringType::find($id);
}
```

`properties` is set to `json_encode([])` (never left `null`) so every entry casts to an empty `Collection` — the job's entry-matching filter (`->where('properties', $choiceListEntry->properties)`) compares two cast `Collection` instances, and this keeps that comparison unambiguous across every fixture in Task 2.

- [x] **Step 2: Verify the file still parses**

Run: `php -l tests/Pest.php`
Expected: `No syntax errors detected in tests/Pest.php`

- [x] **Step 3: Commit**

```bash
git add tests/Pest.php
git commit -m "test: add locale/language-string-type/choice-list-entry fixture helpers"
```

---

### Task 2: Write failing tests reproducing both B11 defects

**Files:**

- Create: `tests/Unit/Jobs/AddMissingChoiceListStringsTest.php`

**Interfaces:**

- Consumes: `AddMissingChoiceListStrings::__construct(XlsformModuleVersion|XlsformTemplate $model)` / `->handle(): void` ([AddMissingChoiceListStrings.php:20](../../src/Jobs/AddMissingChoiceListStrings.php#L20)); `makeXlsformTemplate()`, `addModuleVersion()`, `addChoiceList()`, `addChoiceListEntry()`, `makeLocale()`, `makeLanguageStringType()` from Task 1.

- [x] **Step 1: Write the two failing tests**

```php
<?php

use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Jobs\AddMissingChoiceListStrings;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;

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
```

- [x] **Step 2: Run the tests to verify they fail against current code**

Run: `vendor/bin/pest tests/Unit/Jobs/AddMissingChoiceListStringsTest.php`

Expected:

- Test 1 (`skips an entry with no matching translated counterpart...`) — fails with an error thrown from `Attempt to read property "languageStrings" on null` (or similar), inside `AddMissingChoiceListStrings::handle()`.
- Test 2 (`backfills remaining entries of a partially translated choice list...`) — fails on `expect($targetNo->refresh()->languageStrings)->toHaveCount(1)`, actual count `0` (the whole `$targetList` is invisible to the job's outer query because `yes` already has a string).

- [x] **Step 3: Commit**

```bash
git add tests/Unit/Jobs/AddMissingChoiceListStringsTest.php
git commit -m "test: reproduce B11 null-deref and non-idempotent backfill in AddMissingChoiceListStrings"
```

---

### Task 3: Fix `AddMissingChoiceListStrings`

**Files:**

- Modify: `src/Jobs/AddMissingChoiceListStrings.php:27-81`

**Interfaces:**

- No signature changes — `__construct(XlsformModuleVersion|XlsformTemplate $model)` and `handle(): void` stay the same.

- [x] **Step 1: Replace the `handle()` method body**

Replace lines 27-81 of [AddMissingChoiceListStrings.php](../../src/Jobs/AddMissingChoiceListStrings.php) with:

```php
    public function handle(): void
    {

        $choiceLists = $this->model->choiceLists()
            ->whereHas('choiceListEntries', function (Builder $query) {
                $query->whereDoesntHave('languageStrings');
            })
            ->with('choiceListEntries.languageStrings')
            ->get();

        $choiceLists->each(function (ChoiceList $choiceList) {

            $relationship = 'xlsformModuleVersion.xlsformModule.xlsformTemplate';

            if ($this->model instanceof XlsformModuleVersion) {
                $relationship = 'xlsformModuleVersion';
            }

            $matchingList = ChoiceList::where('list_name', $choiceList->list_name)
                ->whereHas($relationship, function (Builder $query) {
                    $query->where($this->model->getTable() . '.' . $this->model->getKeyName(), $this->model->getKey());
                })
                ->whereHas('choiceListEntries.languageStrings')
                ->with('choiceListEntries.languageStrings')
                ->first();

            if (! $matchingList) {
                return;
            }

            $choiceList->choiceListEntries
                ->reject(fn (ChoiceListEntry $choiceListEntry) => $choiceListEntry->languageStrings->isNotEmpty())
                ->each(function (ChoiceListEntry $choiceListEntry) use ($matchingList) {
                    $matchingEntry = $matchingList->choiceListEntries
                        ->where('name', $choiceListEntry->name)
                        ->where('properties', $choiceListEntry->properties)
                        ->where('cascade_filter', $choiceListEntry->cascade_filter)
                        ->first();

                    if (! $matchingEntry) {
                        return;
                    }

                    $matchingEntry->languageStrings->each(function (LanguageString $languageString) use ($choiceListEntry) {
                        $choiceListEntry->languageStrings()->updateOrCreate([
                            'locale_id' => $languageString->locale_id,
                            'language_string_type_id' => $languageString->language_string_type_id,
                        ], [
                            'text' => $languageString->text,
                        ]);
                    });
                });

        });

    }
```

What changed and why:

- Outer query: `whereDoesntHave('choiceListEntries.languageStrings')` (list qualifies only if **no** entry has strings) → `whereHas('choiceListEntries', fn ($q) => $q->whereDoesntHave('languageStrings'))` (list qualifies if **any** entry is still missing strings). This is what makes the job re-runnable — a list that got partially backfilled by a prior run/attempt stays visible until every entry is done. Added `->with('choiceListEntries.languageStrings')` so the new `reject()` below doesn't N+1 per entry.
- Added `->reject(fn ($e) => $e->languageStrings->isNotEmpty())` before the per-entry loop, so an already-backfilled entry (from a prior run) is left untouched.
- Added the `if (! $matchingEntry) { return; }` guard — the null-deref fix.
- Replaced `$choiceListEntry->languageStrings()->insert($stringsToAdd->toArray())` (a raw multi-row insert with no existence check, which throws the `unique_language_string` constraint violation from [migration 029](../../database/migrations/029_create_language_strings_table.php#L24) on any re-entrant call) with a per-string `updateOrCreate` keyed on `(locale_id, language_string_type_id)` — the same idiom already used in [HasLanguageStrings.php:31](../../src/Models/OdkLink/Traits/HasLanguageStrings.php#L31) for this exact model. Idempotent by construction; the `$stringsToAdd` intermediate collection is no longer needed and is removed.

- [x] **Step 2: Run the tests from Task 2**

Run: `vendor/bin/pest tests/Unit/Jobs/AddMissingChoiceListStringsTest.php`
Expected: both tests pass.

- [x] **Step 3: Run the full suite and static analysis**

Run: `composer test`
Expected: all tests pass (172 + the 2 new ones).

Run: `composer analyse`
Expected: no new PHPStan errors introduced by this file (baseline was 140 per the review doc's remediation note).

- [x] **Step 4: Commit**

```bash
git add src/Jobs/AddMissingChoiceListStrings.php
git commit -m "fix: guard null match and make AddMissingChoiceListStrings re-runnable"
```

---

### Task 4: Update the code review doc

**Files:**

- Modify: `docs/code-reviews/2026-07-06-package-review.md`

- [x] **Step 1: Mark B11 as fixed**

In the "Highest-priority items" table and the B11 section heading, this finding isn't in the top table (B11 is Medium/⚠️, not in the `#|Severity|Status|Finding` table at the top — only B1-B8 are). Just update the B11 section itself.

Change the B11 heading and add a status line, following the same style as B9/B14:

```markdown
### B11 ✅ `AddMissingChoiceListStrings` null-deref + non-idempotent duplicates
```

Add directly after the existing B11 paragraph (before the `### B12` heading):

```markdown
**Status — ✅ fixed (branch `pre-publish-fixes`):** added a `! $matchingEntry` guard so an entry with no translated counterpart is skipped instead of dereferencing null. The outer query now selects choice lists with **any** entry still missing strings (`whereHas('choiceListEntries', fn ($q) => $q->whereDoesntHave('languageStrings'))`) instead of requiring **none** have strings, and the per-entry loop skips already-populated entries and writes via `updateOrCreate` (keyed on `locale_id`/`language_string_type_id`) instead of a raw `insert()` — so a retry after a mid-job failure backfills only what's still missing, without throwing on the `unique_language_string` constraint. Covered by [AddMissingChoiceListStringsTest.php](../../tests/Unit/Jobs/AddMissingChoiceListStringsTest.php).
```

- [x] **Step 2: Update the remediation status note at the top of the doc**

At [2026-07-06-package-review.md:10](../../docs/code-reviews/2026-07-06-package-review.md#L10), change:

```markdown
> **Remediation status (branch `pre-publish-fixes`, updated 2026-07-23):** B1–B9 and B14 fixed. Verified with the full Pest suite (172 passing) and PHPStan (149→140 errors, none new). Per-finding status is noted inline against each item below.
```

to:

```markdown
> **Remediation status (branch `pre-publish-fixes`, updated 2026-07-23):** B1–B9, B11 and B14 fixed. Verified with the full Pest suite and PHPStan, no new errors. Per-finding status is noted inline against each item below.
```

(Drop the stale `172 passing` / `149→140` counts rather than hand-updating them — re-run `composer test` and `composer analyse` from Task 3 Step 3 if you want exact numbers, then substitute them here.)

- [x] **Step 3: Commit**

```bash
git add docs/code-reviews/2026-07-06-package-review.md
git commit -m "docs: mark B11 fixed in the package review"
```
