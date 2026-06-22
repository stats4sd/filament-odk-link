# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`stats4sd/filament-odk-link` is a **Laravel package + Filament v5 plugin** (not a standalone app). It lets a host Laravel/Filament application manage [ODK](https://getodk.org) XLSForms in a harmonised, multi-tenant way: import form templates, let teams configure their own copies, deploy them to ODK Central, and pull submissions back into the host database.

It is built on `spatie/laravel-package-tools` ([FilamentOdkLinkServiceProvider.php](src/FilamentOdkLinkServiceProvider.php)) and targets PHP 8.4, Laravel 13, Filament 5.

## Commands

```bash
composer test                       # run the full Pest suite
vendor/bin/pest tests/ArchTest.php  # run a single test file
vendor/bin/pest --filter="phrase"   # run tests matching a name
composer test-coverage              # Pest with coverage (outputs to build/)
composer analyse                    # PHPStan / Larastan (level 4, scans src + database)
composer format                     # Laravel Pint (custom rules in pint.json)
```

Tests run via **Pest + Orchestra Testbench** — there is no host app, the package boots itself inside Testbench. [tests/TestCase.php](tests/TestCase.php) registers the Filament service providers manually and points the DB at an in-memory `testing` connection. New test files are auto-bound to `TestCase` by [tests/Pest.php](tests/Pest.php).

## Plans

Approved implementation plans live in [docs/plans/](docs/plans/). When work on a plan begins, store the plan there (one file per plan) and keep its status note current. After completing significant work on the plan, update the "`Status:`" line and `### Progress Log` section at the top of the document.

## Configuration & host-app contract

All behaviour is driven by [config/filament-odk-link.php](config/filament-odk-link.php) (env-backed). The package does **not** ship the form-owner or user models — the host app supplies them:

- `ODK_FORM_OWNER_MODEL` (default `App\Models\Team`) — the tenant/owner model. It **must** use the `HasXlsforms` trait ([src/Models/OdkLink/Traits/HasXlsforms.php](src/Models/OdkLink/Traits/HasXlsforms.php)). Only **one** model may own forms; every Xlsform belongs to one.
- `ODK_USER_MODEL` (default `App\Models\User`).
- `ODK_URL`, `ODK_USERNAME`, `ODK_PASSWORD`, `ODK_PLATFORM_PROJECT_ID` — credentials for the single "platform" account on ODK Central that owns every deployed form.
- `submission.process_method` / `foreign_key_process_method` — host-app class+method called to post-process incoming submissions.

Migrations are explicitly ordered and registered by numeric prefix in `getMigrations()` in the service provider — when adding a table, add both the file and its entry there.

## Architecture

### Two Filament plugins
- [OdkLinkAdmin](src/OdkLinkAdmin.php) — platform-admin panel. Discovers resources in `src/Filament/OdkAdmin/Resources` (manage templates, modules, module versions, datasets, choice lists).
- [OdkLinkTeam](src/OdkLinkTeam.php) — tenant-facing panel. **Requires the panel to have tenancy enabled** (throws otherwise). Discovers resources in `src/Filament/OdkTeam/Resources`.

### Domain model (the core to understand)
The form hierarchy lives in `src/Models/OdkLink/`:

- **XlsformTemplate** — a master form uploaded by an admin. Composed of **XlsformModule**s, each with versioned **XlsformModuleVersion**s.
- **Xlsform** — a *team's* deployable instance derived from a template, owned by the form-owner model. This is what gets deployed and collects data.
- Both `Xlsform` and `XlsformTemplate` extend the abstract [HasXlsformDrafts](src/Models/OdkLink/Abstracts/HasXlsformDrafts.php), which is a `spatie/laravel-medialibrary` `HasMedia` model holding the uploaded `.xlsx` (`xlsform_file` collection) and the ODK Central draft state (`odk_id`, draft token, enketo id, QR-code generation).
- Supporting models: **Dataset / DatasetVariable / Entity / EntityValue** (ODK Entities), **ChoiceList / ChoiceListEntry** (lookup lists, localisable per owner), **Submission**, **AppUser**, **OdkProject**, **Platform**, and the `XlsformLanguages/` Language/Locale models.

Behaviour is layered via traits (`src/Models/OdkLink/Traits/`) and interfaces (`Interfaces/`): e.g. `HasXlsforms`, `PublishesToOdkCentral`, `HasSubmissions`, `HasOdkCentralAccount`, `IsLookupList`. Relationships lean heavily on `staudenmeir/eloquent-has-many-deep` and `belongs-to-through`.

### ODK Central client
[OdkLinkService](src/Services/OdkLinkService.php) is registered as a **singleton** and is the single gateway to the ODK Central REST API. It is composed of trait "sub-services" in `src/Services/OdkLinkServices/` — `OdkProjectService`, `OdkFormService`, `OdkUserService`, `OdkFormMediaService`, `OdkSubmissionService`. Auth tokens are cached (`odk-token`, 20h). Inject `OdkLinkService` rather than calling HTTP directly.

### Event-driven import pipeline
Uploading an `.xlsx` triggers Spatie's `MediaHasBeenAddedEvent`, wired in [FilamentOdkLinkEventServiceProvider](src/FilamentOdkLinkEventServiceProvider.php) to [HandleXlsformTemplateAdded](src/Listeners/HandleXlsformTemplateAdded.php). The listener parses the workbook (via `maatwebsite/excel` importers in `src/Imports/XlsformTemplate/`) and dispatches a **chain of queued jobs** (`src/Jobs/`, e.g. `FinishXlsformTemplateImport`, `PrepareSurveyRowPaths`, `ImportAllLanguageStrings`, `FinishChoiceListEntryImport`) that populate survey rows, choice lists, language strings, and locales.

### Deployment & submissions flow
- **Deploy:** `Xlsform`/`XlsformTemplate` model events (`booted()`) and the `src/Jobs/XlsformDeployment/` jobs push drafts/published forms to ODK Central (`DeployDraftXlsformToOdkCentral`, `PublishXlsformOnOdkCentral`, `UpdateXlsformFile`) and fire `XlsformDraftWasDeployed` / `XlsformWasPublished` events. Model `saved` hooks track `draft_needs_update` / `live_needs_update` flags.
- **Collect:** the `odk:poll-for-odk-data` command ([PollForOdkData](src/Commands/PollForOdkData.php)) dispatches `PullSubmissionsFromXlsform` for every active form; `ProcessOdkSubmission` ingests each submission and the host-app `submission.process_method` is invoked. Console commands in `src/Commands/` are auto-registered from the directory by the service provider.

## Conventions

- Models, jobs, and listeners use Eloquent **model events** (`booted()`) heavily for side effects — when changing a model, check its `booted()` method for cascading deploy/import/notification logic before assuming a change is isolated.
- The `ArchTest` enforces no `dd`/`dump`/`ray` left in `src`.
- Frontend assets are pre-built into `resources/dist/` and registered via `FilamentAsset`; build config is `package.json` (esbuild/tailwind).
