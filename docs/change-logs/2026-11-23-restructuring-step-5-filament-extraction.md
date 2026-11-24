# Restructuring step 5: Filament package extraction

Implements [docs/plans/2026-11-23-restructuring-step-5-filament-extraction.md](../plans/2026-11-23-restructuring-step-5-filament-extraction.md), roadmap step 5 in [RESTRUCTURING.md](../../RESTRUCTURING.md). Branch `restructure-step-5-filament-package`.

## Outcome

- `packages/odk-link-core` is headless. Filament, Livewire and their transitive packages (36 in all) are gone from its `vendor/` and lock.
- `packages/odk-link-filament` (`stats4sd/odk-link-filament`) holds the Filament UI and requires the core by path.
- PHP namespaces, the view namespace `filament-odk-link::`, the asset key, publish tags and the config file are unchanged. Both packages map `Stats4sd\FilamentOdkLink\` to their own `src/` until the step 7 rename.

## Changes

### Moves (history preserved, `git log --follow` works)
- `src/Filament`, `src/Forms`, `src/Testing`, `src/OdkLinkAdmin.php` → `packages/odk-link-filament/src/`, same relative paths.
- `resources/views/filament`, `resources/css`, `resources/dist/filament-odk-link.css`, `package.json`, `package-lock.json`, `.prettierrc` → `packages/odk-link-filament/`.
- `tests/Unit/Support/FilamentAdaptersTest.php` → `packages/odk-link-filament/tests/Unit/`.

### Deleted
- The umbrella `FilamentOdkLinkServiceProvider`. Each package's provider is discovered directly.
- Unreferenced assets: the `emails/role_invite` and `emails/team_invite` views, `filament/css-listing.blade.php`, the empty `lang/en/odk-link.php`, the empty `resources/js/index.js` and `resources/dist/filament-odk-link.js`, and the `dev:scripts`/`build:scripts` npm scripts that called the `bin/build.js` deleted in step 2. The provider no longer registers a JS asset.

### Core
- `composer.json`:
  - drops `filament/filament`, `filament/spatie-laravel-media-library-plugin` and `simplesoftwareio/simple-qrcode` (QR codes are only rendered in the moved views);
  - discovers `OdkLinkCoreServiceProvider`.
- **Hidden dependencies found.** Removing Filament exposed two runtime dependencies that only arrived transitively:
  - `spatie/laravel-medialibrary`: the models implement `HasMedia`.
  - `kirschbaum-development/eloquent-power-joins`: `XlsformChoicesExport` and `XlsformSurveyExport` call `leftJoinRelationship()`. PHPStan flagged this. The tests passed without the package, so those export queries have no test coverage.

  Both are now direct requirements.
- Tests:
  - The base `TestCase` boots only Excel, MediaLibrary and the core provider.
  - The obsolete `PanelRegistry` mocks were removed from 10 test files. Step 4 already removed the `Filament::hasTenancy()` scope calls they guarded.
- Architecture tests:
  - `ShippingBoundary` has no UI allowlist any more: every core shipping file must be free of `Filament\`, `Livewire\` and the moved namespaces.
  - The Pest arch expectation now covers the whole `Stats4sd\FilamentOdkLink` namespace instead of a list of sub-namespaces.
- PHPStan baseline:
  - The 10 UI entries moved to the Filament package.
  - One existing entry in `GenerateSubmissions`, about the undefined `Xlsform::$odk_project_id` (a pre-existing bug), now also matches a narrower receiver type. Locally PHPStan infers `Xlsform` for `Xlsform::find($this->argument(...))`, while CI infers `Collection<int, Xlsform>|Xlsform`. The two runs have identical PHPStan, Larastan and Laravel versions. The cause was not traced at the time. It is the same single error, still suppressed, not fixed. Traced in step 6: CI booted Larastan without the core's service provider, so the command signature was unknown and `$this->argument()` was `mixed`. A committed `packages/odk-link-core/testbench.yaml` fixes it and the entry is back to the single form; see the step 6 change log.
- README and `CLAUDE.md` describe the core as headless and point to the Filament package.

### Filament package
- `OdkLinkFilamentServiceProvider::register()` registers `OdkLinkCoreServiceProvider` before binding the Filament adapters. The adapters therefore win whatever order package discovery uses; previously the umbrella provider fixed the order.
- Own Pest + Testbench suite (16 tests):
  - the moved adapter tests;
  - provider boot (core registered once, adapters bound, views and stylesheet registered);
  - plugin registration (five resources, two widgets);
  - core-registered-first ordering;
  - a shipping-boundary arch test (no `App\`, test namespaces or Spatie permission).
- PHPStan level 4 with the moved baseline entries. `databaseMigrationsPath` points at the core migrations through the symlinked vendor path. Plus Pint, README and `CLAUDE.md`.

### Workspace, reference app, CI
- Root `composer.json`:
  - requires both packages;
  - adds `test:filament`, `analyse:filament`, `analyse:all` and `format:filament`;
  - `install:packages` installs both packages.

  The root lock was also stale against the Laravel Excel 4 upgrade (PR #172) and was refreshed with `-W`.
- `apps/reference`:
  - requires `stats4sd/odk-link-filament` by path;
  - the lock change is limited to the two path packages;
  - `PackageBootTest` asserts that both providers are registered once;
  - the README is updated.
- `.github/workflows/filament-ci.yml`: copy of `core-ci.yml` with job names prefixed `filament`. It is not in the `dev` ruleset yet.
- Dependabot covers `/packages/odk-link-filament`. The workflows README is updated.

## Verification

| Check | Result |
| --- | --- |
| Core `composer test` | 237 passed (243 before, minus 6 moved adapter tests) |
| Core `composer analyse`, `pint --test`, `composer validate --strict` | pass |
| Core `composer show filament/filament` / `livewire/livewire` | not installed |
| Filament `composer test` | 16 passed |
| Filament `composer analyse`, `pint --test`, `composer validate --strict` | pass |
| Reference `composer test` | 22 passed, 1 skipped (unchanged) |
| Reference `php artisan filament:assets` | publishes `filament-odk-link-styles.css` |
| Root `composer validate --no-check-publish`, `composer test:all`, `composer analyse:all` | pass |

Not done locally: a browser check of `/admin`. The reference suite covers the resource index and create pages, and asset publishing was checked.

## Follow-ups
- `XlsformChoicesExport` and `XlsformSurveyExport` query paths have no tests. That gap hid the missing power-joins dependency.
- The empty `FilamentOdkLink` class and facade remain in core.
- Correction (step 6 review): an earlier version of this list called `staudenmeir/belongs-to-through` unused. It is used by `Country`, `Submission`, `ChoiceListEntry`, `LanguageString` and `XlsformModuleVersionLocale`; do not remove it.
- Deviation not recorded at the time: five unreferenced views moved instead of being deleted (`forms/components/form-review`, `infolists/components/team-qr-code`, `tables/columns/draft-qr-code`, `tables/columns/team-datasets-required`, `widgets/custom-odk-templates-widget`), as did the unused `ClickableLink` and `DraftTestingQrCode` components. Step 7 deletes them.
- The Filament `ArchTest` scan of `resources/views` is ineffective: Blade without `<?php` tokenises as inline HTML, so `ShippingBoundary` sees no class names. Fixed in step 7.
- Step 7 renames, listed in the roadmap.
