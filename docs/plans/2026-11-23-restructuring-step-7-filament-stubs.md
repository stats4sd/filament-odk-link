# Restructuring Step 7: Filament UI as stubs plus an install command

**Status:** Not Started

Plan refreshed 2026-11-24 against `dev` at `fd19643`, after step 6 merged (PR #177). Changes in the refresh: inventory re-checked and paths updated, the `DatasetTable` item dropped because PR #179 already landed it, commit 1 details tightened after a trial run (reverted, nothing committed), the fate of the package's panel tests added to commit 4, the baseline count corrected, and the doc list in commit 6 completed.

Depends on step 6 (renames, done). Namespaces, view names and config keys below use the post-rename names: `Stats4sd\OdkLink\Filament\`, `odk-link::`, `config('odk-link.*')`.

## Pending outside this step (found 2026-11-24)

Do both before starting. Step 6b is a hard prerequisite: this step freezes the UI into host code, so the UI must be in its redesigned shape first.

- **Step 6b, package review and redesign** ([plan](2026-11-27-package-review-and-redesign.md)). Replaces the earlier "forward-port `staging-hotfix-20261026`" item: on 2026-11-27 it was decided not to port that branch but to redesign the affected code and carry the hotfix behaviour through a [requirements ledger](2026-11-27-hotfix-requirements-ledger.md). Step 6b changes UI files this step turns into stubs (the template upload and replace actions, the choice list resource) and decides whether team-panel stubs are in scope, so regenerate this plan's inventory (file counts, views, strings) after it lands. The #124 fix for the "Replace XLSForm" action in `XlsformTemplateTable` arrives through step 6b, not through a port.
- **Laravel Excel 4 manual admin-panel check** ([plan](2026-11-23-laravel-excel-4-upgrade.md), step 5). The import and export UI is frozen into host code by this step, so check it before the stubs are published.

## Context

Roadmap step 7 in `RESTRUCTURING.md`, from the architecture review's Option C ([docs/code-reviews/2026-07-06-architecture-package-vs-app.md](../code-reviews/2026-07-06-architecture-package-vs-app.md)). The UI is published into each host's `app/Filament/...` by an install command. After that the host owns and edits the files. The package keeps only the runtime pieces that a host should not fork.

Decisions taken (2026-11-23):
- The command is `odk-link:install-filament --panel=<id>`. It calls the core's `odk-link:install` (config and migrations) first.
- `OdkLinkAdmin` is deleted. The host's own panel discovery finds the published resources and widgets. Keeping the plugin as an optional "unpublished" mode would mean two live copies of the UI.
- Team-panel (tenant) stubs are out of scope. See "Team panel: what exists today" below.

## Inventory (2026-11-23, re-checked 2026-11-24)

51 PHP files in the Filament package `src/` and 15 Blade views. Since step 6 the resources live at `src/OdkAdmin/Resources/`, the widgets at `src/Widgets/`, and `ChoiceListResource` is the only resource not in its own directory.
- **Library code, stays in the package (12 files):**
  - `OdkLinkAdmin` (deleted by this step)
  - the service provider
  - `FilamentCurrentOwnerResolver` and `FilamentOperationNotifier` (contract adapters)
  - `HtmlBlock`, `ClickableLink`, `DraftTestingQrCode` (form components; the last two are unused)
  - `RedirectsToListAfterSave` (trait)
  - `TestsFilamentOdkLink` (empty Livewire mixin)
- **App-shaped, becomes stubs (42 files):**
  - 5 resources
  - 13 pages
  - 5 relation managers
  - 9 schema classes
  - 8 table classes
  - 2 widgets
- **Views:**
  - 7 app-facing views are in use: the 2 widget views, the 2 media-count columns, the 2 `xlsform-section-schema-modal-link` views and `draft-testing-qr-code` (infolist entry). Commit 1 deletes the media-count pair and merges the modal-link pair, so 4 become stubs. The QR view calls the `QrCode` facade, so `simplesoftwareio/simple-qrcode` stays a requirement of this package and reaches the host transitively.
  - `html-block` stays in the package.
  - 7 are dead: `form-review`, `team-qr-code`, `draft-qr-code`, `team-datasets-required`, `custom-odk-templates-widget`, plus the views of the two unused components.
- **Hard-coded English:** about 170 literals in PHP and about 15 in Blade. Only 3 `__()` calls exist (bare `__('File')` and `__('Title')`, no namespace), and there are no lang files anywhere. The two navigation groups are `ODK Forms and Datasets` (four resources) and `Survey and Datasets` (`ChoiceListResource` only); decide in commit 2 whether the second is intentional or a leftover.
- **Admin-panel assumptions that the stubs will carry as they are:** `XlsformTemplateResource::getFormOwner()` and `ListXlsformTemplates` both read `Platform::first()`. No `filament.admin.*` route names remain.

## Implementation

### Commit 1: cleanup before freezing anything into host code
- Delete:
  - the 7 dead views;
  - `ClickableLink` and `DraftTestingQrCode`;
  - the empty `TestsFilamentOdkLink` mixin and its `Testable::mixin()` call.
- ~~`DatasetTable.php` hard-coded `filament.admin.…` route.~~ Done in PR #179, which also added `tests/Feature/DatasetTableTest.php` and a `PanelTestCase` with a panel called `odk`.
- `OdkUrlAlertWidget`: add `canView()` returning true only when `config('odk-link.odk.url')` is blank. Today discovery puts it on every dashboard. `ListXlsformTemplates::getHeaderWidgets()` already makes the same check before adding the widget; with `canView()` in place that check is redundant, so reduce it to returning the widget.
- The `required-data-media-count` and `required-fixed-media-count` column views: four `count()` queries per row run from `@php`. Replace them and delete both views:
  - `->modifyQueryUsing()` on the table with `withCount(['requiredFixedMedia', 'attachedFixedMedia', 'requiredDataMedia', 'attachedDataMedia'])`;
  - two `TextColumn`s on the `attached_*_count` attributes, `formatStateUsing()` for "attached / required", `->color()` for success or danger, `->size(TextSize::ExtraSmall)`;
  - read the required count with `$record->getAttribute(...)`, because `checkModelProperties` rejects the undeclared `*_count` properties;
  - `tests/Smoke/ProviderBootTest.php` lists `filament.tables.columns.required-data-media-count` in its view dataset; swap it for a view that survives.
  - Trialled on 2026-11-24: suite and PHPStan stayed green. There is no test that renders the template table, so add one (a template created with `forceCreateQuietly`, as the reference `AdminPanelTest` does).
- The two `xlsform-section-schema-modal-link` views (one used once by a `ViewEntry`, the other three times by a `ViewEntry` and two `ViewField`s). `schema` is a `collection` cast on both models, not a relation, so there is no count query here, only a lazy `rootSection` load and sentence-building in Blade. Build the sentence in PHP and pass it with `->viewData(fn ($record) => ['summary' => …])` (`viewData()` accepts a closure). Both views then reduce to `{{ $summary }}` plus `{{ $getAction('viewSchema') }}`, so merge them into one. That also puts the sentence where commit 2 can key it.
- Fix the Filament `ArchTest` Blade gap. `ShippingBoundary` tokenises Blade as inline HTML and sees nothing. Compile the views first with `Blade::compileString()`, or regex-scan view files for forbidden prefixes.

### Commit 2: translation layer
- `packages/odk-link-filament/resources/lang/en/*.php`, loaded with `loadTranslationsFrom(..., 'odk-link')`. One file per resource plus `common.php` for shared labels and `navigation.php` for the two navigation groups.
- Key every user-facing literal in PHP and Blade, including notification titles and bodies, wizard step names, tab names, validation messages and `HtmlString` blocks. Use `:placeholders` for sentences that build in counts.
- Translations stay in the package rather than being published as stubs. Hosts override per key through `lang/vendor/odk-link/{locale}/*.php`, so a package update can add new keys without touching host files. Hosts that want full control publish them with tag `odk-link-translations`.
- Set the resource model labels (`getModelLabel()` / `getPluralModelLabel()`) and navigation labels through keys too.
- Test: a translation-completeness test in the package. Every `__('odk-link::…')` key found under `stubs/` and `src/` exists in `en`.

### Commit 3: stubs
- `git mv` the 40 resource files, 2 widgets and remaining app-facing views into `packages/odk-link-filament/stubs/`, mirroring the host layout:
  ```
  stubs/
  ├── app/Filament/Resources/{ChoiceLists,Datasets,XlsformModules,XlsformModuleVersions,XlsformTemplates}/...
  ├── app/Filament/Widgets/{AvailableOdkTemplatesWidget,OdkUrlAlertWidget}.php
  └── resources/views/filament/odk-link/...
  ```
- Stubs are valid PHP in the `App\Filament\...` namespace, as Breeze and Jetstream do it. They are not autoloaded, not in PHPStan's paths, and excluded from the `App\` boundary rule, which still applies to their other forbidden prefixes. Normalise `ChoiceListResource` into a `ChoiceLists/` directory to match the other four.
- Stub views use the host's view names (`filament.odk-link.…`). Stub PHP points at them.
- Stub PHP keeps importing runtime pieces from the package: `Stats4sd\OdkLink\Filament\Forms\Components\HtmlBlock` and `Stats4sd\OdkLink\Filament\Traits\RedirectsToListAfterSave`. Those are the package's public API from now on, so changing them is a breaking change.
- Tailwind: `resources/css/index.css` `@source` lines point at `stubs/**` and the package views. The built CSS covers the classes the stubs ship with. Classes a host adds to its own copies need the host's own Filament theme; document this.
- `OdkLinkAdmin` deleted; nothing else in the package registers resources or widgets.
- The `odk-link-views` publish tag stays but now covers only `html-block`. Say so in the README, since hosts that published views before the upgrade keep stale copies under `resources/views/vendor/odk-link`.

### Commit 4: install command
- `Stats4sd\OdkLink\Filament\Commands\InstallFilamentCommand`, `odk-link:install-filament {--panel=} {--force} {--no-core}`.
  - Resolves the panel through `Filament::getPanel($id)` and fails if it does not exist. `--panel` is required unless exactly one panel exists.
  - Target resource and widget directories and namespaces come from the panel's `getResourceDirectories()` / `getResourceNamespaces()` and `getWidgetDirectories()` / `getWidgetNamespaces()` (Filament 5, `Panel/Concerns/HasComponents.php`). It fails with a clear message if the panel has no resource discovery configured. If there are several, it uses the first and says so.
  - Rewrites `namespace App\Filament\Resources` / `App\Filament\Widgets`, and the matching `use` lines, to the panel's namespaces. It copies views into `resources/views/filament/odk-link/`.
  - Skips existing files and lists them, unless `--force` is given.
  - Unless `--no-core` is given, calls `odk-link:install` first.
  - Prints the next steps: run migrations, configure contracts in `config/odk-link.php`, rebuild the theme if the host has one.
- Existing tests that depend on the resources living in `src/`: `Smoke/PluginTest` (deleted with the plugin), and `Feature/DatasetTableTest` with `PanelTestCase` and `Panel/OdkPanelProvider` (PR #179). The last three exist to prove nothing assumes a panel called `admin`, which the reference app cannot prove because its panel is `admin`. Keep that guarantee here: the install test below targets the `odk` panel, registers an autoloader for the temp path, and renders `ListDatasets` from the installed copy. If that proves too brittle under Testbench, fall back to verification step 3 and say so in the change log.
- Tests (Testbench), replacing `Smoke/PluginTest`:
  - install into a temp app path for a test panel with `discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')`;
  - assert the file count and the rewritten namespaces;
  - `php -l` on every written file;
  - a second run without `--force` skips everything;
  - `--force` overwrites;
  - an unknown panel fails.

### Commit 5: reference app consumes the published output
- `php artisan odk-link:install-filament --panel=admin --no-core` in `apps/reference`, and commit `app/Filament/**` and `resources/views/filament/odk-link/**`.
- `AdminPanelProvider`: drop `->plugin(OdkLinkAdmin::make())`. Its existing `discoverResources`/`discoverWidgets` now find the published files.
- `AdminPanelTest`: import `App\Filament\Resources\...` instead of package classes. It is the only reference test that imports the package's resource classes; `HostContractsTest` and `PackageBootTest` import only the adapters and the provider, which stay. Fill in the view and edit render `todo` if the package issues listed in the app README allow it.
- Add Larastan to the reference app (`composer analyse`, `reference phpstan` job). The Filament package baseline has 8 entries, all under `src/OdkAdmin/Resources/`; they move to the app's baseline (or get fixed on the way), leaving the package baseline empty.
- **Drift check** in `reference-ci.yml`: a step runs `php artisan odk-link:install-filament --panel=admin --no-core --force`, then `git diff --exit-code -- apps/reference/app/Filament apps/reference/resources/views/filament/odk-link`. Consequence: the reference copy must stay pristine stub output, and host-style customisation is demonstrated elsewhere, if at all. Job name prefixed `reference`.

### Commit 6: docs
- Filament package README: install flow, what the host owns versus what stays in the package, translation overrides, the theme note.
- `UPGRADING.md` (created in step 6): existing hosts using `->plugin(OdkLinkAdmin::make())` run `odk-link:install-filament` and remove the plugin call.
- `RESTRUCTURING.md`: tick step 7, resolve the "Drift check" and "What stays in the Filament package at runtime" open decisions, and update the notes. Changes to stub files now need a re-run of the installer in `apps/reference` with the output committed, or the drift check fails.
- Every tracked doc that still describes the plugin (checked 2026-11-24): root `CLAUDE.md` and `AGENTS.md`, `packages/odk-link-filament/{README,CLAUDE}.md`, `packages/odk-link-core/{README,CLAUDE}.md`, `apps/reference/README.md`, and the `AdminPanelProvider` docblock.
- `.github/workflows/README.md`: the row for the new reference jobs. Add `reference phpstan` to the `dev` ruleset once it has run green.
- Plan Status → Completed and a change log.

## Team panel: what exists today

For the record, so that a later step can decide whether tenant stubs are worth building:

- **Reference app.**
  - `TeamPanelProvider` (`apps/reference/app/Providers/Filament/TeamPanelProvider.php`): panel `team`, path `/team`, `->tenant(Team::class)`. It discovers `app/Filament/Team/{Resources,Pages,Widgets}`, which do not exist, so it shows only the dashboard and account widget.
  - `TeamPanelTest` covers login, the tenant redirect, and 404s for missing or other users' teams.
- **Mechanism that already works for tenants.**
  - `FilamentCurrentOwnerResolver` returns the current tenant when the current panel has tenancy, and null otherwise.
  - The core global scopes on `Xlsform`, `Submission`, `ChoiceListEntry` and `Locale` filter by `HelperService::getCurrentOwner()`.
  - `HostContractsTest` asserts that the current owner follows `Filament::setTenant` on the `team` panel.
  - So tenant scoping of core data is in place, independent of any UI.
- **UI code that is tenant-aware already.**
  - `DatasetForm` uses a hidden `owner_id` defaulting to the tenant when the panel has tenancy, and an owner `Select` otherwise.
  - It also uses a tenant-scoped unique rule.
- **Admin-only assumptions in the current UI.**
  - `XlsformTemplateResource::getFormOwner()` returns `Platform::first()`.
  - `ListXlsformTemplates` reads `Platform::first()?->odkProject`.
  - The resource docblock says "use this resource for an admin panel".
  - No resource sets `$isScopedToTenant` or a tenant ownership relationship.
- **Team-shaped leftovers, deleted in commit 1.** They hint at the features a team panel was meant to have:
  - `team-qr-code` showed the ODK Collect app-user QR code with setup instructions for a team's devices;
  - `custom-odk-templates-widget` listed team-owned custom templates and linked to them through the owner's slug;
  - `team-datasets-required` counted the datasets a template needs the team to supply.
- **What tenant stubs would be useful for:** a team-facing panel where a team deploys available templates as its own `Xlsform`s, sees its submissions, supplies the datasets its forms require, and links devices by QR code. That is a feature design (which resources, which actions, what a team may edit), not a conversion of the existing admin UI. If it is built, it would ship as a second stub set installed with `--panel=team`, with its own `Team/` resource namespace.

## Out of scope
- Team-panel stubs (above).
- Fixing the package issues listed in the reference app README, beyond what commit 1 touches.
- Release plumbing (step 8).

## Verification
1. Filament package: `composer test` (install-command tests, translation completeness, arch with a working Blade scan), `composer analyse`, Pint. `src/` no longer contains resources, pages or widgets.
2. Reference app: `composer test`, the new `composer analyse`, and the drift check run locally (installer with `--force`, then `git diff --exit-code`).
3. Fresh host: in a scratch Laravel 13 + Filament 5 app with a non-`admin` panel id, `composer require` both packages by path, run `odk-link:install-filament --panel=<id>`, migrate, and load every resource index, create and view page in the browser.
4. Override check: add one key to `lang/vendor/odk-link/en/...` in the reference app (not committed), and the label changes.
5. CI: all three workflows green, including the new reference jobs. Since 2026-11-24 the `dev` ruleset requires `filament tests (PHP 8.4)`, `filament phpstan`, `filament pint` and `reference tests (PHP 8.4)` as well as the core checks, so the PR cannot merge with those red.
