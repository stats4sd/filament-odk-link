# Restructuring Step 5: extract `packages/odk-link-filament`

**Status:** Completed

Accepted and implemented 2026-11-23. Change log: [docs/change-logs/2026-11-23-restructuring-step-5-filament-extraction.md](../change-logs/2026-11-23-restructuring-step-5-filament-extraction.md). Deviations from the plan: core also needed `kirschbaum-development/eloquent-power-joins` as a direct dependency, and the Filament package gained a core-registered-first ordering test.

## Context

Roadmap step 5 in `RESTRUCTURING.md`. Step 4 cut the actual host/UI coupling, so the core now reaches the UI in only two places:

- `src/FilamentOdkLinkServiceProvider.php`: an umbrella provider that registers both `OdkLinkCoreServiceProvider` and `Filament\OdkLinkFilamentServiceProvider`.
- `src/OdkLinkAdmin.php`: the Filament plugin, which sits in the core's root namespace.

Everything else Filament-related sits under `src/Filament`, `src/Forms`, `src/Testing` and `resources/`. Goal: move all of it into a second package so the core installs, tests and analyses with **no Filament or Livewire in its vendor**. With Filament absent, PHPStan's class-not-found errors enforce the boundary.

Decisions taken (2026-11-23):
- **Renames deferred.** The new package's Composer name is `stats4sd/odk-link-filament`. PHP namespaces, the config file name, the view namespace `filament-odk-link::`, the asset package key and the core Composer name (`stats4sd/filament-odk-link`) all stay as they are. All renames land together in a later step. As a result, the moved files keep identical paths relative to `src/` and `resources/`, and the moves need almost no code edits.
- **The Filament package gets its own Pest + Testbench suite** plus `filament-ci.yml`. Panel and page rendering stays in the reference app.
- **Unreferenced assets are deleted** during the move rather than carried over.

Branch: `restructure-step-5-filament-package` off `dev`.

## Target layout

```
packages/odk-link-filament/
├── composer.json          stats4sd/odk-link-filament; path repo ../odk-link-core
├── src/
│   ├── Filament/          (moved) OdkAdmin/, Widgets/, Support/ adapters, Traits/, OdkLinkFilamentServiceProvider.php
│   ├── Forms/             (moved) Components/
│   ├── Testing/           (moved) TestsFilamentOdkLink.php
│   └── OdkLinkAdmin.php   (moved)
├── resources/
│   ├── views/filament/    (moved, minus css-listing.blade.php)
│   ├── css/index.css      (moved)
│   └── dist/filament-odk-link.css (moved; the empty .js is deleted)
├── tests/                 new: Pest.php, TestCase.php, Models/{Team,User}.php, Database/migrations, Unit/FilamentAdaptersTest.php (moved), Smoke/
├── package.json, package-lock.json, .prettierrc   (moved; build:scripts / dev:scripts removed)
└── phpunit.xml.dist, phpstan.neon.dist, phpstan-baseline.neon, pint.json, .editorconfig, .gitattributes, LICENSE.md, README.md, CLAUDE.md
```

Autoload: `"Stats4sd\\FilamentOdkLink\\": "src/"`. This is the **same PSR-4 prefix as core**. Composer merges directories that share a prefix across packages, so `Stats4sd\FilamentOdkLink\OdkLinkAdmin` resolves from either package without code changes. The overlap is temporary and disappears with the namespace rename. Tests use `"Stats4sd\\FilamentOdkLink\\Filament\\Tests\\": "tests/"`.

Because relative paths are preserved, these keep working unchanged: `OdkLinkAdmin`'s `__DIR__ . '/Filament/...'` discovery, the provider's `__DIR__ . '/../../resources/...'` paths, `css/index.css`'s `../../vendor/filament/...` import, and the existing PHPStan baseline `path:` values.

## Implementation

### Commit 1: pure `git mv` (no content edits, so rename detection and blame survive)
- `git mv packages/odk-link-core/{src/Filament,src/Forms,src/Testing,src/OdkLinkAdmin.php}` → `packages/odk-link-filament/src/`
- `git mv` `resources/views/filament`, `resources/css`, `resources/dist`, `package.json`, `package-lock.json`, `.prettierrc` → `packages/odk-link-filament/`
- `git mv tests/Unit/Support/FilamentAdaptersTest.php` → `packages/odk-link-filament/tests/Unit/`
- `git rm` the dead files:
  - `resources/views/emails/*`, `resources/views/filament/css-listing.blade.php`, `resources/views/.gitkeep`
  - `resources/lang/en/odk-link.php`
  - `resources/dist/filament-odk-link.js`, `resources/dist/.gitkeep`
  - `src/FilamentOdkLinkServiceProvider.php` (the umbrella provider; each package auto-discovers its own provider instead)

The tree will not be green at this commit. That is acceptable inside the PR; CI judges the PR head.

### Commit 2: core becomes headless
- `packages/odk-link-core/composer.json`:
  - Remove `filament/filament`, `filament/spatie-laravel-media-library-plugin` and `simplesoftwareio/simple-qrcode` (QR codes are only rendered in the moved blade views).
  - Add `spatie/laravel-medialibrary: ^11.0`. Core models use it directly; today it only arrives transitively through the Filament plugin. 11.23.13 is locked.
  - `extra.laravel.providers` → `Stats4sd\FilamentOdkLink\OdkLinkCoreServiceProvider`.
  - Then run `composer update` with the removed and added packages so the lock drops Filament and Livewire.
- `tests/TestCase.php`: replace the 13-provider list with `ExcelServiceProvider`, `MediaLibraryServiceProvider` and `OdkLinkCoreServiceProvider`, the same set as `tests/Headless/HeadlessTestCase.php`. Leave merging the two base classes for later.
- Delete the stale `PanelRegistry`/`Panel` Mockery blocks from the ~11 Unit tests that carry them (`PollForOdkDataTest`, `SubmissionControllerTest`, `HasXlsformsTest`, `OdkFormServiceTest`, …). Step 4 removed `Filament::hasTenancy()` from the global scopes; grep confirms no `Filament::` or `hasTenancy` remains in core `src/`.
- `tests/Headless/BootstrapTest.php`: drop the `FilamentOdkLinkServiceProvider` import and assertion. Keep the `Filament\`/`Livewire\` prefix assertions.
- `tests/ArchTest.php`:
  - In the boundary test, remove the `$ui` allowlist, so every core shipping file is checked as non-UI.
  - Add the root namespace `Stats4sd\FilamentOdkLink` to the `not->toUse(['Filament','Livewire',…])` expectation, or switch the target to the whole namespace.
- `phpstan-baseline.neon`: cut the 9 entries whose paths are under `src/Filament/`. They move to the Filament baseline. Leaving them would trip unmatched-ignore errors.
- Core `.gitattributes` and `README.md`:
  - Drop references to the removed resources.
  - README: the Filament UI now lives in `stats4sd/odk-link-filament`, and `OdkLinkAdmin` comes from there.
- Core `CLAUDE.md`: rewrite the "One Filament plugin" section to point at the new package.

### Commit 3: Filament package scaffolding and tests
- `composer.json`:
  - name `stats4sd/odk-link-filament`, type `library`
  - path repo `../odk-link-core`, with a `versions` option pinning `stats4sd/filament-odk-link` to `dev-dev`, as the root does
  - require: `php ^8.4`, `stats4sd/filament-odk-link: dev-dev`, `filament/filament ^5.0`, `filament/spatie-laravel-media-library-plugin ^5.0`, `simplesoftwareio/simple-qrcode ^4.2`
  - require-dev: mirror the core's (Testbench 11, Pest 4 + arch/laravel plugins, Larastan, Pint)
  - `post-autoload-dump: testbench package:discover`
  - scripts `test`, `analyse` and `format`
  - `extra.laravel.providers` → `Stats4sd\FilamentOdkLink\Filament\OdkLinkFilamentServiceProvider`
- `OdkLinkFilamentServiceProvider`:
  - `register()` first calls `$this->app->register(OdkLinkCoreServiceProvider::class)`. That makes the adapter bindings always override the core's null defaults, whatever order package discovery uses. The call is idempotent, so discovery loading core first is harmless.
  - Remove the `Js::make(...)` registration because the file is gone.
  - Keep the CSS asset key `stats4sd/filament-odk-link` (deferred rename).
- `tests/TestCase.php`:
  - Filament and Livewire providers (the list removed from core), Excel, MediaLibrary and `OdkLinkFilamentServiceProvider`.
  - Load core migrations from `vendor/stats4sd/filament-odk-link/database/migrations` plus a local owner-table migration.
  - Team and User test doubles copied from core `tests/Models`. Core test classes are not autoloadable from another package.
- Tests:
  - the moved `FilamentAdaptersTest`, with its namespace and provider references updated
  - new `Smoke/ProviderBootTest`: the provider registers views under `filament-odk-link`, the CSS asset is registered, and the core contracts resolve to the Filament adapters
  - new `Smoke/PluginTest`: a panel with `OdkLinkAdmin::make()` discovers the five resources and two widgets
  - `ArchTest`: no `dd`/`dump`/`ray`; no `App\`, `Spatie\Permission` or `'Super Admin'`
- PHPStan:
  - `phpstan.neon.dist`: level 4, path `src`
  - `phpstan-baseline.neon`: the 9 entries cut from core
- `pint.json`, `.editorconfig`, `.gitattributes` and `LICENSE.md`: copied from core.
- `README.md`: short, covering install, `->plugin(OdkLinkAdmin::make())` and the view publish tag.
- `CLAUDE.md`: commands, plus a pointer to the root.

### Commit 4: workspace, reference app, CI
- Root `composer.json`:
  - add `stats4sd/odk-link-filament` to `versions` and `require` (smoke check, per the existing decision)
  - add `test:filament`, `analyse:filament` and `format:filament`
  - `install:packages` installs both packages
  - `test:all` adds `test:filament`
  - update the lock
- `apps/reference`:
  - require `stats4sd/odk-link-filament: dev-dev` and add it to `versions`
  - run `composer update stats4sd/filament-odk-link stats4sd/odk-link-filament` and commit the lock
  - `tests/Feature/PackageBootTest.php`: assert `OdkLinkCoreServiceProvider` and `OdkLinkFilamentServiceProvider` instead of the deleted umbrella
  - README "Known package issues" and install notes: mention both packages
- `.github/workflows/filament-ci.yml`:
  - copy of `core-ci.yml` with `working-directory`/`PKG` = `packages/odk-link-filament`
  - job names prefixed `filament composer validate`, `filament tests (PHP 8.4|8.5)`, `filament phpstan` and `filament pint`, so they cannot collide with the required `core-ci` check names
  - CI resolves core through the path repo inside the checkout, so no extra steps are needed
- `.github/workflows/README.md`: document `filament-ci`. It stays out of the `dev` ruleset until it has run green a few times, the same policy as `reference-ci`.

### Commit 5: docs
- `RESTRUCTURING.md`:
  - Status line: steps 2–5 complete. Also fix the stale step 4 text, which says the PR is pending; it was merged as #154.
  - Tick step 5 with a summary.
  - Notes:
    - the shared PSR-4 prefix
    - provider ordering
    - "core changes need `composer update` in both the Filament package and the reference app"
  - Add "rename namespaces/packages/config" explicitly to step 7, or as a step 6.5.
- Root `CLAUDE.md`: add a layout-table row for `packages/odk-link-filament/` and fix the core row ("Laravel package and Filament plugin" becomes headless core).
- Plan file status → Completed, plus a change log `docs/change-logs/2026-11-23-restructuring-step-5-filament-extraction.md`.

## Out of scope (note, do not fix)
- Deferred renames.
- Stub conversion and the translation layer (step 6).
- The empty `FilamentOdkLink` facade/class in core.
- ~~The unused `staudenmeir/belongs-to-through` dependency.~~ Correction (step 6): it is used. Five core models use `Znck\Eloquent\Traits\BelongsToThrough`.
- Merging `TestCase` and `HeadlessTestCase`.
- Package issues listed in the reference app README.

## Verification
1. **Core is Filament-free:**
   - `cd packages/odk-link-core && composer show filament/filament` fails, and so does `composer show livewire/livewire`.
   - `grep -rE 'Filament\\\\|Livewire' src database config routes` finds nothing beyond `FilamentOdkLink` namespace hits.
2. **Core suite:** `composer test` passes. The count drops by the moved adapter tests from today's 234. Also run `composer analyse` and `vendor/bin/pint --test`.
3. **Filament package:** `composer install`, `composer test`, `composer analyse` and `vendor/bin/pint --test` all pass.
4. **Root:**
   - `composer validate --no-check-publish`
   - `composer validate --strict` in both packages
   - `composer install:all && composer test:all`
5. **Reference app:**
   - `composer test` gives 22 passed and 1 skipped, as before.
   - `php artisan about` lists both providers.
   - Manual check: `php artisan serve`, log into `/admin`, confirm the five ODK resources, both widgets and the package CSS load.
6. **CI on the PR:** `core-ci` (required checks), `filament-ci` and `reference-ci` all green, except the known PHP 8.5 install failure if it still applies. `git log --follow` on a moved file shows pre-move history.
