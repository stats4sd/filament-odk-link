# CLAUDE.md

# Guidelines

The project working patterns are included from the root [CLAUDE.md](../../CLAUDE.md) (`.claude/` lives at the monorepo root, not in this package).

# Project-Specific:

## Commands

```bash
composer analyse                    # PHPStan / Larastan (level 4, scans src + database). New errors must be fixed, not added to phpstan-baseline.neon
```

## Configuration & host-app contract

All behaviour is driven by [config/filament-odk-link.php](config/filament-odk-link.php) (env-backed). The package does **not** ship the form-owner or user models — the host app supplies them:

- `ODK_FORM_OWNER_MODEL` (no package default) — the tenant/owner model. It **must** implement `FormOwner`, normally using the `HasXlsforms` trait ([src/Models/OdkLink/Traits/HasXlsforms.php](src/Models/OdkLink/Traits/HasXlsforms.php)). Only **one** model may own forms; every Xlsform belongs to one.
- `ODK_USER_MODEL` (no package default) — an Eloquent model implementing `PlatformUser`, normally using Laravel `Notifiable`.
- `ODK_URL`, `ODK_USERNAME`, `ODK_PASSWORD`, `ODK_PLATFORM_PROJECT_ID` — credentials for the single "platform" account on ODK Central that owns every deployed form.
- `contracts.submission_processor`, `contracts.role_resolver`, `contracts.current_owner_resolver` and `contracts.operation_notifier` resolve container-injected host services. See the package README for defaults, validation and migration. Populated legacy static callback settings are rejected.

Migrations are explicitly ordered and registered by numeric prefix in `getMigrations()` in `OdkLinkCoreServiceProvider` — when adding a table, add both the file and its entry there.

## Architecture

### One Filament plugin
- There is no team-facing plugin in the package today. Tenant UI lives in host apps; extracting it is roadmap steps 5 and 6 in the root `RESTRUCTURING.md`.

### Domain model (the core to understand)
The form hierarchy lives in `src/Models/OdkLink/`:

- **XlsformTemplate** — a master form uploaded by an admin. Composed of **XlsformModule**s, each with versioned **XlsformModuleVersion**s.
- **Xlsform** — a *team's* deployable instance derived from a template, owned by the form-owner model. This is what gets deployed and collects data.
- Both `Xlsform` and `XlsformTemplate` extend the abstract [HasXlsformDrafts](src/Models/OdkLink/Abstracts/HasXlsformDrafts.php), which is a `spatie/laravel-medialibrary` `HasMedia` model holding the uploaded `.xlsx` (`xlsform_file` collection) and the ODK Central draft state (`odk_id`, draft token, enketo id, QR-code generation).

### ODK Central client
[OdkLinkService](src/Services/OdkLinkService.php) is registered as a **singleton** and is the single gateway to the ODK Central REST API. It is composed of trait "sub-services" in `src/Services/OdkLinkServices/` — `OdkProjectService`, `OdkFormService`, `OdkUserService`, `OdkFormMediaService`, `OdkSubmissionService`. Auth tokens are cached (`odk-token`, 20h). Inject `OdkLinkService` rather than calling HTTP directly.

### Event-driven import pipeline
Uploading an `.xlsx` triggers Spatie's `MediaHasBeenAddedEvent`, wired in [FilamentOdkLinkEventServiceProvider](src/FilamentOdkLinkEventServiceProvider.php) to [HandleXlsformTemplateAdded](src/Listeners/HandleXlsformTemplateAdded.php). The listener parses the workbook (via `maatwebsite/excel` importers in `src/Imports/XlsformTemplate/`) and dispatches a **chain of queued jobs** (`src/Jobs/`, e.g. `FinishXlsformTemplateImport`, `PrepareSurveyRowPaths`, `ImportAllLanguageStrings`, `FinishChoiceListEntryImport`) that populate survey rows, choice lists, language strings, and locales.

### Deployment & submissions flow
- **Deploy:** `Xlsform`/`XlsformTemplate` model events (`booted()`) and the `src/Jobs/XlsformDeployment/` jobs push drafts/published forms to ODK Central (`DeployDraftXlsformToOdkCentral`, `PublishXlsformOnOdkCentral`, `UpdateXlsformFile`) and fire `XlsformDraftWasDeployed` / `XlsformWasPublished` events. Model `saved` hooks track `draft_needs_update` / `live_needs_update` flags.
- **Collect:** the `odk:poll-for-odk-data` command ([PollForOdkData](src/Commands/PollForOdkData.php)) dispatches `PullSubmissionsFromXlsform` for every active form; `ProcessOdkSubmission` ingests each submission and the configured `SubmissionProcessor` runs after successful ingestion. Console commands in `src/Commands/` are auto-registered from the directory by the service provider.

## Conventions

- Models, jobs, and listeners use Eloquent **model events** (`booted()`) heavily for side effects — when changing a model, check its `booted()` method for cascading deploy/import/notification logic before assuming a change is isolated.
