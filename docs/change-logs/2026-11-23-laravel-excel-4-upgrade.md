# Change log: Laravel Excel 4 / PhpSpreadsheet 5 upgrade

Implements [docs/plans/2026-11-23-laravel-excel-4-upgrade.md](../plans/2026-11-23-laravel-excel-4-upgrade.md). Branch `laravel-excel-update`, off `restructuring-prep-for-ui-split`.

## Dependencies

- Core `composer.json`: `maatwebsite/excel` `^3.1` → `^4.0`.
- Core lock: Laravel Excel 3.1.70 → 4.0.7, PhpSpreadsheet 1.30.6 → 5.12.0. Also refreshed the rest of the lock to the highest versions, to match what CI resolves (`dependency-versions: highest`). Notable: larastan 3.11 → 3.15, phpstan 2.2.13 → 2.3.7, Laravel 13.30 → 13.43, Filament 5.8.1 → 5.12.0. A partial `-W` update left larastan and Laravel out of step, which made baseline messages drift locally in ways CI would never see.
- Reference app lock: same Excel/PhpSpreadsheet versions, plus the core's transitive bumps.

## Code

- Return types required by 4.0's natively typed concerns:
  - `XlsImport::collection()` and `XlsformTemplateValidator::collection()`: `: void`
  - `XlsformChoicesExport::query()`: `: Builder`
  - `XlsformSurveyExport::styles()` and `XlsformTemplateTranslationsExport::styles()`: `void` → `?array`, returning `null`
- `Export` / `Import` marker interfaces on the multi-sheet-only classes: `XlsformWorkbookExport`, `SurveyExport`, `XlsformTemplateWorkbookImport`.
- `GenerateSubmissions`:
  - Read the stored workbook back with a bare `Import` on the `local` disk it was written to. It used to pass an export into `Excel::toArray()`, which is a `TypeError` under 4.0.
  - Removed a dead `array_combine() === false` check. It can't be false on PHP 8, and the baselined error's wording changed under the new typing.
- `XlsImport` implements `SkipsUnknownSheets`. **Pre-existing bug, not caused by the upgrade:** since 9f6b5d1 (June 2026), reading any XLSForm without an `entities` sheet threw `SheetNotFoundException` in 3.1 too. That broke `OdkFormService` (deploy/test on ODK Central) and `XlsformTemplate` entity-list sync for forms without entities. Callers already guard with `has('entities')`.
- PHPStan baseline: removed two stale entries (Scout `Builder` return type on `XlsformChoicesExport::query()`, and the `GenerateSubmissions` `array_combine` comparison). No entries added.

## Tests

- `tests/ArchTest.php`: every class in `Exports` must implement `Export` and every class in `Imports` must implement `Import`. Confirmed it fails when a marker is removed.
- `tests/Unit/Services/UpdateXlsformTitleInFileTest.php`: real PhpSpreadsheet round-trip on a copy of `valid-form.xlsx`. Covers the title and slug `form_id`, keeping a deployed `odk_id`, and the missing-settings-sheet error.
- `tests/Unit/Imports/XlsImportTest.php`: reads survey/choices/settings from a fixture without an `entities` sheet. Also runs `XlsformValidationHelper::validateTypeOrOther()` through `XlsformTemplateValidator`.
- `tests/Unit/Exports/XlsformTemplateTranslationsExportTest.php`: `Excel::raw()` end-to-end through the real writer. Checks headings, header style and sheet protection.
- `tests/Unit/Exports/XlsformSurveyExportStylesTest.php`: `styles()` on a real worksheet, checking group/repeat row fills. Uses `newInstanceWithoutConstructor()` because the constructor uses MySQL-only `json_keys()`.
- The full workbook export (`XlsformWorkbookExport`, `XlsformChoicesExport`) and `SurveyExport` can't run end-to-end on the SQLite test DB because of `json_keys()`. They need the manual check below.
- `tests/fixtures/generate-xlsx-fixtures.php` runs cleanly under PhpSpreadsheet 5. The committed fixtures were left unchanged.

## Verification (local, PHP 8.4)

- Core: Pest 243 passed; PHPStan no errors; Pint clean; `composer validate --strict` OK.
- Reference app: Pest 22 passed, 1 skipped; Pint clean.
- Not yet done: push and confirm CI, and the manual admin-panel check (template upload, XLSform download, translations export, submissions export).

## Docs

- Core `CHANGELOG.md`: Unreleased entry covering the breaking Laravel Excel 4 requirement, the advice to drain queues, and the `entities`-sheet fix.
- Core `README.md`: install note on the Laravel Excel 4 requirement.
