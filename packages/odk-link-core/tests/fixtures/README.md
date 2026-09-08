# Test fixtures

Sample inputs for the test suite. Text-based fixtures (CSV / JSON) are committed here directly. The XLSForm `.xlsx` fixtures are binary and are added alongside the import tests in Phase 2 (see `docs/plans/smoke-and-unit-tests.md`).

## Present

- `lookup.csv` — a small CSV with a header row, embedded newline, and a UTF-8 BOM, exercising `HelperService::importCsvFileToCollection()` (header combine, newline stripping, BOM removal, trailing-blank-line handling).
- `submission.json` — a representative ODK Central submission payload (including the system fields `HelperService::getOdkVariablesToIgnore()` strips), for `ProcessOdkSubmission` / `OdkSubmissionService` tests.

## To add in Phase 2 (XLSForm import tests)

- `valid-form.xlsx` — minimal valid single-language XLSForm (survey + choices + settings sheets).
- `multilang-form.xlsx` — valid form with two label languages.
- `entities-form.xlsx` — form defining an entity / choice list from data.
- `invalid-form.xlsx` — malformed workbook to assert `XlsformTemplateValidator` rejection.
- `submissions.csv` — a CSV export of submissions for the CSV ingestion path.
