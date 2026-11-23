# Upgrade Laravel Excel 3.1 → 4.0 (PhpSpreadsheet 1.x → 5.x)

**Status**: In Progress

All automated steps are done (PR #172). CI is green via manual dispatch: core-ci phpstan, pint, validate and tests on PHP 8.4 and 8.5; reference-ci validate, tests and pint. One extra pre-existing bug was found and fixed: `XlsImport` threw on XLSForms with no `entities` sheet. Remaining: the manual admin-panel check in step 5. See [change log](../change-logs/2026-11-23-laravel-excel-4-upgrade.md).

**Branch**: `laravel-excel-update`, off `restructuring-prep-for-ui-split`. PR targets `restructuring-prep-for-ui-split` so PR #154 goes green once merged.

## Why

`core-ci` → `phpstan` (PHP 8.4) fails on PR #154:

```
Call to method getSheetByName() of deprecated class PhpOffice\PhpSpreadsheet\Spreadsheet:
This branch of PhpSpreadsheet is deprecated. Please migrate to release 5 or later ASAP.
```

- CI resolves `dependency-versions: highest`, so it gets PhpSpreadsheet 1.30.9, which marks the whole 1.x `Spreadsheet` class `@deprecated`. `phpstan-deprecation-rules` reports it (`src/Services/UpdateXlsformTitleInFile.php`).
- Local passes only because `composer.lock` pins 1.30.6.
- `maatwebsite/excel` 3.1.x requires `phpoffice/phpspreadsheet ^1.30.5`, so there is no 3.1 route to PhpSpreadsheet 5. Laravel Excel 4.0 (4.0.7, 2026-11-17) requires PHP `^8.3`, Laravel `^12 || ^13`, PhpSpreadsheet `^5.11`. All compatible with our constraints.
- Side effect, out of scope: PhpSpreadsheet 1.30.7+ also caps PHP `<8.5`, which is what breaks the informational `tests (PHP 8.5)` job. The upgrade should fix that too, but PHP 8.5 is not a target here.

Upstream guide: `UPGRADE-4.x.md` on the `4.x` branch of SpartnerNL/Laravel-Excel.

## Audit of `packages/odk-link-core` against 4.0

### A. Fatal signature mismatches (class won't load under 4.0)

4.0 adds native return types to every concern interface. A child method with no return type, or an incompatible one, is a fatal error at class load.

| File | Current | 4.0 interface | Fix |
| --- | --- | --- | --- |
| `src/Imports/XlsImport.php:16` | `collection(Collection $collection)` | `ToCollection::collection(Collection): void` | add `: void` |
| `src/Imports/XlsformTemplate/XlsformTemplateValidator.php:12` | `collection(Collection $collection)` | same | add `: void` |
| `src/Exports/XlsformExport/XlsformChoicesExport.php:41` | `query()` (untyped) | `FromQuery::query(): Builder\|EloquentBuilder\|Relation` | add `: Builder` (Eloquent) |
| `src/Exports/XlsformExport/XlsformSurveyExport.php:164` | `styles(Worksheet $sheet): void` | `WithStyles::styles(Worksheet): ?array` | `: ?array`, `return null;` |
| `src/Exports/XlsformTemplateTranslationsExport.php:109` | `styles(Worksheet $sheet): void` | same | `: ?array`, `return null;` |

Already compatible (checked): `collection(): Collection` (narrower than `Enumerable`), `model(): ?X` (narrower than `Model|array|null`), `uniqueBy(): array`, `map($row): array` (untyped param is wider than `mixed`), `backgroundColor(): string`, `styles(): array`, `sheets(): array`, `chunkSize/batchSize/querySize(): int`, `columnWidths(): array`, `headings(): array`, `title(): string`. `RegistersEventListeners` in 4.0 calls non-static `afterImport()` via `[$this, $name]`, which matches our instance methods.

### B. `Export` / `Import` marker interfaces

The `Excel` facade/`Excel` class methods now type-hint `Export $export` / `Import $import`. Classes that implement only `WithMultipleSheets` (not itself an `Export`/`Import`) will throw `TypeError` when passed in.

| Class | Implements today | Add |
| --- | --- | --- |
| `Exports/XlsformExport/XlsformWorkbookExport` | `ShouldQueue, WithMultipleSheets` | `Export` |
| `Exports/SurveyExport` | `WithMultipleSheets` | `Export` |
| `Imports/XlsformTemplate/XlsformTemplateWorkbookImport` | `ShouldQueue, WithChunkReading, WithEvents, WithMultipleSheets` | `Import` |

`XlsformTemplateHeadingRowImport` extends `HeadingRowImport`, which implements `Import` in 4.0. No change.

### C. Pre-existing bug the new types will surface

- `src/Commands/GenerateSubmissions.php:51` calls `Excel::toArray(new XlsformWorkbookExport($xlsform), $filePath)`. `toArray` takes an `Import`; passing an export was always wrong and becomes a `TypeError`. Fix: read the stored file back with a plain import (e.g. `new XlsImport` or `IOFactory::load(...)->getSheetByName('survey')->toArray()`). Check whether this dev command is still used before deciding how much effort to put in. If not, consider removing it in a separate change.

### D. Exportable / Importable property redeclarations

None. Five classes `use Importable`; none redeclare `$disk`, `$readerType`, `$filePath` or `$output`. No class uses `Exportable`.

### E. Return-type changes on `Importable::queue()` / `Excel::queue()`

Now `PendingDispatch|PendingBatch`. We chain `->chain([...])` on both (`Listeners/HandleXlsformTemplateAdded.php:75`, `Models/OdkLink/Xlsform.php:405`). Fine while we don't implement `ShouldBatch`. PHPStan may need a narrow assertion or a baseline entry if it complains that `chain()` doesn't exist on `PendingBatch`.

### F. Direct PhpSpreadsheet usage (1.x → 5.x)

All APIs used still exist in 5.x:

- `UpdateXlsformTitleInFile`: `IOFactory::load`, `getSheetByName`, `getRowIterator`, `getCellIterator`, `setIterateOnlyExistingCells`, `getValue`, `getCoordinate`, `setCellValue('A2', …)`, `IOFactory::createWriter($s, 'Xlsx')`.
- Export `styles()` bodies: `getStyle(range)->applyFromArray()`, `getFont()->setBold()`, `Worksheet::toArray()`, `Coordinate::stringFromColumnIndex`, constants `Fill::FILL_SOLID`, `Alignment::VERTICAL_TOP`, `Protection::PROTECTION_UNPROTECTED`.
- `tests/fixtures/generate-xlsx-fixtures.php`: `setCellValue([$col, $row], …)`, which is the 2.0+ array form.
- No use of the removed `*ByColumnAndRow` methods.

The risk is behaviour, not API: stricter typing inside PhpSpreadsheet (e.g. `trim($cell->getValue())` when a cell holds `RichText` or `null`). Covered by the smoke tests in step 4.

### G. Config and reference app

- `config/excel.php` has no key changes in 4.0. Neither the core nor the reference app publishes it.
- The reference app gets Laravel Excel only through the core path repo, so it needs a lock refresh only.

### H. PHPStan baseline

`reportUnmatchedIgnoredErrors` is at its default (`true`). Entries that stop matching will fail the run:

- `XlsformChoicesExport::query() has invalid return type Laravel\Scout\Builder`. Goes away once `query()` is typed.
- `Unknown parameter $diskName in call to static method Excel::store()` (`OdkLinkService.php`, count 2). 4.0's `Excel::store` declares `$diskName` natively, so this probably stops matching.
- Any entries tied to 3.1 docblock types may shift.

## Steps

1. **Dependencies** (`packages/odk-link-core`)
   - `composer.json`: `"maatwebsite/excel": "^4.0"`.
   - `composer update maatwebsite/excel phpoffice/phpspreadsheet -W`. Review the lock diff for collateral bumps (`composer/pcre`, `maennchen/zipstream-php`, `markbaker/*` removal, etc.).
2. **Code fixes**: sections A, B, C above.
3. **Static analysis**
   - `composer analyse:core` (or `vendor/bin/phpstan analyse`). Fix new real errors. Drop stale baseline entries by hand rather than regenerating the whole baseline, so unrelated debt stays visible.
   - `vendor/bin/pint`.
4. **Tests**
   - Run the existing suite (`composer test:core`). Current Excel coverage: `EntityExportTest`, `XlsformSettingsExportTest`, `SheetWiringTest`, the `Xlsform*Import*` tests, `HandleXlsformTemplateAddedTest`.
   - Add smoke tests for the untested classes this upgrade touches, writing a real `.xlsx` (not `Excel::fake()`) so PhpSpreadsheet styling runs:
     - `XlsformWorkbookExport` → `Excel::store` to a temp disk, reopen, assert `survey`/`choices`/`settings` sheets exist. This exercises `XlsformSurveyExport::styles()` and `XlsformChoicesExport::query()`/`styles()`.
     - `XlsformTemplateTranslationsExport` → store and assert header fill and unprotected range.
     - `SurveyExport` → `Excel::raw` returns non-empty bytes.
     - `UpdateXlsformTitleInFile::process` against a copy of `tests/fixtures/valid-form.xlsx`, asserting `form_id`/`form_title` values.
     - `XlsformValidationHelper` against `valid-form.xlsx` (uses `XlsformTemplateValidator`).
   - Regenerate the fixtures with `tests/fixtures/generate-xlsx-fixtures.php` under PhpSpreadsheet 5 and confirm the output matches the committed files or is functionally the same. Commit only if needed.
5. **Reference app** (`apps/reference`)
   - `composer update stats4sd/filament-odk-link maatwebsite/excel phpoffice/phpspreadsheet -W`, then commit `composer.lock`.
   - `composer test:reference`.
   - Manual check in the admin panel: upload an XLSform template (import pipeline + queued jobs), download a built XLSform (workbook export + styles), download translations export, download submissions export.
6. **CI**
   - Push and confirm `core-ci` `phpstan`, `tests (PHP 8.4)`, `pint`, `validate` and `reference-ci` are all green.
   - `tests (PHP 8.5)` is informational. Note the result in the PR but don't block on it.
7. **Docs**
   - Core `README.md`/CHANGELOG: note the Laravel Excel 4 requirement. This is a breaking change for host apps that use Laravel Excel 3.1 directly.
   - Change log in `docs/change-logs/`, then mark this plan Completed with a link to it.

## Out of scope

- PHP 8.5 support as a merge requirement.
- Adopting new 4.0 features (`WithColumns`, `ShouldBatch`, `#[Queue]` attributes).
- Raising PHPStan level or clearing unrelated baseline debt.

## Risks

- **Downstream break**: host apps that require `maatwebsite/excel:^3.1` directly can't install the new core version. Call this out in release notes.
- **Queued jobs in flight**: serialized 3.1 import/export jobs on a live queue may not unserialize under 4.0. Drain queues before deploying hosts.
- **Silent styling regressions** in exported XLSforms. Mitigated by the step 4 smoke tests and the step 5 manual download check.
