# Restructuring: filament-odk-link → odk-link monorepo

**Status**: In Progress (steps 2–6 complete, step 7 (stubs) next, see Roadmap) **Branch**: one branch per step off `dev` (`restructure` for step 2, `reference-app` for step 3, `restructure-step-5-filament-package` for step 5, `restructure-step-6-renames` for step 6) **Started**: 2026-09-08 **Origin**: [docs/code-reviews/2026-07-06-architecture-package-vs-app.md](docs/code-reviews/2026-07-06-architecture-package-vs-app.md) and its companion [docs/code-reviews/2026-07-06-package-review.md](docs/code-reviews/2026-07-06-package-review.md). Documentation was added to version control in PR #153; implementation records now accompany the tracked roadmap.

This file is the working log for the restructure. Keep the Status line and the Roadmap checkboxes current. Delete this file, or fold it into a change log under `docs/change-logs/`, when the roadmap is complete.

## Goal

Stop feature work being split three ways (package server-side, app-specific behaviour, UI half in each). The review diagnosed three fixable causes rather than anything intrinsic to "being a package":

1. **The package boundary is fictional.** Shipping code imports `App\Models\*`, scans the host `App\Models` namespace via `ClassFinder`, and hard-codes `Role::findByName('Super Admin')`.
2. **The packaged UI has no seams.** Resources hard-wire schema/table classes, widgets hold logic in blade views, there is no translation layer. Real UI work escapes into the host app.
3. **Every change is a two-repo, two-release dance.** Change core, tag, bump app, test, find a gap, repeat.

Forking a base app per client was considered and rejected: the apps are mostly shared with a little different, and the ODK Central integration is exactly the code you least want in N drifting copies.

## Declared end state

```
odk-link/                         (this repo, renamed from filament-odk-link)
├── composer.json                 workspace shell: path repo → packages/*, delegating scripts
├── packages/
│   ├── odk-link-core/            headless domain core, versioned dependency
│   └── odk-link-filament/        Filament UI as publishable starter-kit stubs + install command
├── apps/
│   └── reference/                thin Laravel + Filament app consuming both packages by path
├── docs/                         tracked plans, change-logs and code-reviews
└── .github/workflows/            per-package CI + subtree split on tag
```

- **`odk-link-core`**: models, `OdkLinkService` and sub-services, jobs, imports/exports, events, commands, migrations, and the contracts the host must implement. No `Filament\`, no `App\`, no hard-coded roles. Tested with Pest + Orchestra Testbench in isolation.
- **`odk-link-filament`**: resources, pages, schemas, tables, widgets, views, assets. Published into the host's `app/Filament/...` by an install command. After publishing, the host owns and edits the files. Tested via the reference app, since Filament and Livewire tests need a real panel.
- **`apps/reference`**: what a client app looks like after installing both packages. Owns a `Team` model and a `User` model, wires the admin and team panels. Doubles as the manual test surface and the UI test host. Has its own `composer.json` with `"url": "../../packages/*"`.
- **Publishing**: the monorepo is the source of truth. Packagist needs one repository per package, so CI pushes read-only subtree splits to `stats4sd/odk-link-core` and `stats4sd/odk-link-filament` on tag. Consumers outside the monorepo `composer require` tagged versions from those.

## Decisions made

| Decision | Choice | Why |
| --- | --- | --- |
| Fresh repo vs in place | Restructure in place on a branch with `git mv` | Keeps 587 commits of history and blame, the open issues, and PR references. The hard work is the boundary, not the file locations, and a blank repo invites rewriting the core while splitting it. |
| Vendor layout | Per-package `vendor/`; root scripts delegate via `composer --working-dir=packages/<name> <script>` | A path-required package's `autoload-dev` and `post-autoload-dump` are ignored for non-root packages, so running the core suite from a root vendor would mean duplicating test autoload and the Testbench discover step. Per-package vendor also matches how the split repos are tested. |
| Root `require` of core | Kept, as a smoke check | Proves the package's dependency set resolves and gives the IDE and PHPStan one vendor to index. Costs a duplicate Filament install at root. Drop it if that becomes annoying. |
| Path repo URL | Glob `packages/*` | Adding `odk-link-filament` later needs only a `require` line and `:filament` script variants. |
| Breaking changes | One release, one migration guide | Package rename, namespace rename, config file rename, plugin class rename all land together with the split. Consumers face one `composer require` change either way. |
| Contracts before UI extraction | Step 4 before step 5 | Purging host coupling is useful to existing consumers even if the split stalls, and it reveals the dependency graph the extraction has to cut. |
| Reference app and published UI | Reference app holds the published copy; stubs in the package are the source of truth | Two copies by design. Drift must be caught mechanically, see Open decisions. |
| Root `composer.lock` | Committed. `composer.lock` was removed from the root `.gitignore` during step 2 | Workspace of type `project`, so the lock is meaningful. Side effect: `packages/odk-link-core/composer.lock` is now also tracked because the package has no `.gitignore` of its own. Add one if the package lock should stay untracked. |
| Renames before stubs | Step 6 renames, step 7 stubs (decided 2026-11-23, after the step 5 review) | Published stubs embed namespaces, view names, config keys and translation keys. Renaming after publishing would break every host copy, so names are made final before anything is published. |
| UI install command | `odk-link:install-filament --panel=<id>`, calls core's `odk-link:install` first | Core's spatie install command becomes `odk-link:install` after the rename, so the UI command needs a distinct name. |
| `OdkLinkAdmin` plugin | Deleted in step 7 | Once the host owns the resources its panel discovery finds them; a plugin mode alongside stubs would mean two live copies of the UI. |
| Package, namespace and config names | `stats4sd/odk-link-core` / `Stats4sd\OdkLink\Core`, `stats4sd/odk-link-filament` / `Stats4sd\OdkLink\Filament`, config `odk-link.php`, view namespace `odk-link::`; env keys unchanged (confirmed 2026-11-23) | Full mapping in the step 6 plan. |
| Outstanding bug work | Deferred | The four ⚠️ items in the package review (B12–B15) are independent of packaging. They are parked rather than fixed first. Track them before the split release. |

## Open decisions

- **Drift check for published UI.** Proposed in the step 7 plan: a `reference-ci` step re-runs the installer with `--force` in `apps/reference` and fails on `git diff`.
- **What stays in the Filament package at runtime.** Decided: no plugin. Proposed in the step 7 plan: provider, contract adapters, `HtmlBlock`, `RedirectsToListAfterSave`, CSS and translations stay; resources, pages, schemas, tables, widgets and their views become stubs.
- **Team-panel stubs.** Out of scope for step 7. The step 7 plan records what tenant support exists today and what a team UI would be for.
- **GitHub repo rename.** `filament-odk-link` → `odk-link`. GitHub redirects the old URL. Do it when the split repos are created so the names line up.
- **Which host apps consume this.** Unknown count. Affects how much ceremony the migration guide needs.

## Roadmap

Each step is its own PR with the core test suite green.

- [ ] **1. Close out the remaining ⚠️ items from the package review.** Deferred by decision, not done. Track them as issues before the split release.
- [x] **2. Mechanical move into `packages/odk-link-core`.** Merged as PR #149.
  - [x] `git mv` the whole package into `packages/odk-link-core/` (all files renamed, history preserved). `stubs/.gitkeep`, `test.txt`, `bin/build.js` and `.phpunit.cache` were deleted rather than moved.
  - [x] Root `composer.json` written and validated. Path repo resolves the core as `dev-restructure` in a dry run.
  - [x] Run the core suite from its new location and confirm green: `composer test` at root, or `composer test` inside `packages/odk-link-core`.
  - [x] Fix `.github/workflows/*`. They still reference root `composer.json` and `vendor/bin/pest`. They are also stale independent of the move: `run-tests.yml` targets branch `main`, PHP 8.1/8.2, Laravel 10, while the package targets PHP 8.4, Laravel 13 and merges to `dev`. Rewrite rather than patch. Plan: [docs/plans/2026-09-08-github-workflows-monorepo.md](docs/plans/2026-09-08-github-workflows-monorepo.md) (local only, `docs/` is gitignored).
  - [x] Add a root `CLAUDE.md` pointing agents at the package-level `packages/odk-link-core/CLAUDE.md` and this file, and repair the `@.claude/...` includes in the package `CLAUDE.md` (the `.claude/` directory stayed at root). The untracked root `AGENTS.md` from before the move is gone. **Correction (step 3):** this was ticked in PR #149 but the root `CLAUDE.md` was never committed and the package includes were still broken. Both landed in the step 3 PR.
  - [x] Commit, PR to `dev`.
- [x] **3. Add `apps/reference`.** Fresh Laravel 13 + Filament 5 install with `Team` and `User`, an `admin` panel loading `OdkLinkAdmin` and a tenant `team` panel, requiring core by path. Published package config and migrations committed, seeded roles/user/team/platform, 17 smoke tests, `reference-ci.yml`. Plan: [docs/plans/2026-09-08-reference-app.md](docs/plans/2026-09-08-reference-app.md) (local only). Details in [apps/reference/README.md](apps/reference/README.md).
- [x] **4. Purge host-app coupling behind contracts.** Merged as PR #154. Canonical `FormOwner`/`PlatformUser`, injectable submission processing and notification recipients, validated current-owner and notification-delivery seams, explicit model registry, independent core registration and Filament adapters replace the actual host/UI coupling. Reference models/config demonstrate these contracts. Architecture tests cover shipping source, migrations, config and routes. Core tests: 234 passed; reference: 22 passed, one existing skip; both Pint checks and Composer validation pass. A 2026-11-23 follow-up review fixed three issues ([review](docs/code-reviews/2026-11-23-step-4-pr-154-review.md)). Plan: [docs/plans/2026-09-18-restructuring-step-4-host-contracts.md](docs/plans/2026-09-18-restructuring-step-4-host-contracts.md).
- [x] **5. Extract `packages/odk-link-filament`.** `git mv` of `src/Filament`, `src/Forms`, `src/Testing`, `OdkLinkAdmin`, the Filament views, CSS source and build, and the npm config into `packages/odk-link-filament` (Composer name `stats4sd/odk-link-filament`). Namespaces unchanged. The umbrella `FilamentOdkLinkServiceProvider` and unreferenced assets were deleted. Core no longer installs Filament or Livewire. Removing Filament exposed two hidden transitive dependencies, `spatie/laravel-medialibrary` and `kirschbaum-development/eloquent-power-joins`, which the core now requires directly. The UI package has its own Testbench suite, PHPStan, Pint and `filament-ci.yml`. Core tests: 237 passed. Filament tests: 16 passed. Reference: 22 passed, one existing skip. Plan: [docs/plans/2026-11-23-restructuring-step-5-filament-extraction.md](docs/plans/2026-11-23-restructuring-step-5-filament-extraction.md). Change log: [docs/change-logs/2026-11-23-restructuring-step-5-filament-extraction.md](docs/change-logs/2026-11-23-restructuring-step-5-filament-extraction.md).
- [x] **6. Renames.** Core is `stats4sd/odk-link-core` / `Stats4sd\OdkLink\Core`, the UI is `Stats4sd\OdkLink\Filament` (its redundant `src/Filament/` level flattened), config `odk-link.php`, view namespace and publish tags `odk-link`, install command `odk-link:install`, asset key `stats4sd/odk-link-filament`, broadcast names `OdkLink.*`. A core migration rewrites pre-rename class names in polymorphic type columns; the planned morph map was dropped (see change log). `packages/odk-link-core/UPGRADING.md` started. First commit carries the step 5 review fixes. Core tests: 239 passed. Filament: 16. Reference: 22 passed, one existing skip. Change log: [docs/change-logs/2026-11-23-restructuring-step-6-renames.md](docs/change-logs/2026-11-23-restructuring-step-6-renames.md). Plan: [docs/plans/2026-11-23-restructuring-step-6-renames.md](docs/plans/2026-11-23-restructuring-step-6-renames.md).
- [ ] **7. Convert the Filament package to stubs plus an install command.** `odk-link:install-filament --panel=...`; `OdkLinkAdmin` deleted. Dead-code cleanup first, translation layer, stubs, install command. Wire `apps/reference` to consume the published output, add Larastan there and the drift check. Plan: [docs/plans/2026-11-23-restructuring-step-7-filament-stubs.md](docs/plans/2026-11-23-restructuring-step-7-filament-stubs.md).
- [ ] **8. Release plumbing.** Replace the `dev-dev` pins and path repos in the packages' published `composer.json` with version constraints; decide lockstep or independent versioning; make `tests (PHP 8.5)` a required check. Subtree split workflow to read-only repos, register both on Packagist, rename the GitHub repo, finish the migration guide from `UPGRADING.md`, tag. Step 1 (B12–B15 as issues) must be done before this step.

## Notes for whoever picks this up

- The move happened on branch `restructure`, merged to `dev` as PR #149 on 2026-09-08 (roadmap step 2 complete).
- Root `.gitignore` patterns for `vendor` and `node_modules` are unanchored, so they cover nested package directories. `docs/` became tracked in PR #153. `composer.lock` is no longer ignored anywhere.
- The core's `post-autoload-dump` runs `testbench package:discover`. That only fires when the core is the Composer root, which is why tests must run inside `packages/odk-link-core` and not against the root vendor.
- Testbench stays for the core even after the reference app exists. The reference app is for the UI layer and manual checks, not a replacement for isolated package tests.
- Project working patterns (plans in `docs/plans/`, change logs in `docs/change-logs/`, status lines) are in `.claude/` at the root, which did not move with the package. `CLAUDE.md` did move, so the `@.claude/...` includes at the top of `packages/odk-link-core/CLAUDE.md` now point at paths that do not exist relative to that file. Fix as part of the root `CLAUDE.md` task in step 2. When steps 3 onward begin, write a plan file per step.
- CI lives in `.github/workflows/core-ci.yml` and runs inside `packages/odk-link-core`. [.github/workflows/README.md](.github/workflows/README.md) is the tracked reference for every workflow and the repo settings they rely on. Per-package suites for later steps are a copy of `core-ci.yml` with a different working directory.
- `tests (PHP 8.5)` has passed in `core-ci` and `filament-ci` since PR #172 (Laravel Excel 4 / PhpSpreadsheet 5) removed the PhpSpreadsheet 1.x blocker. It is not a required check yet; step 8 makes it one.
- The `dev` ruleset ("Tests Pass") requires `tests (PHP 8.4)`, `phpstan` and `pint`, with the strict up-to-date policy: a PR must be rebased or merged with `dev` before it can merge, so expect to re-run CI after any other PR lands. Direct pushes to `dev` are blocked by the same rule; every change goes through a PR, including doc-only edits like this one.
- Repo-level "Allow auto-merge" is off. Dependabot's `gh pr merge --auto` therefore errors out rather than merging; Actions bumps still need a manual merge click after CI. Enable it in repo settings if hands-off Actions bumps are wanted; the ruleset now provides the CI gate that was missing.
- `PROJECT_ACCESS_TOKEN` was rotated on 2026-09-08 and the issue auto-assign workflow succeeds again. If it starts failing with `Bad credentials`, the PAT has expired: rotate, do not debug.
- `filament-ci.yml` (step 5) is a copy of `core-ci.yml` with the working directory changed and every job name prefixed `filament`. **Prefix the job names in any new workflow**: required status checks match by check-run name across all workflows, so a second job called `pint` would be indistinguishable from the required `core-ci` one and could block merges. `filament-ci` is not in the `dev` ruleset yet; add `filament tests (PHP 8.4)`, `filament phpstan` and `filament pint` once they have run green a few times.
- `reference-ci` is not in the `dev` ruleset yet. Add `reference tests (PHP 8.4)` once it has run green a few times. It installs from the committed `apps/reference/composer.lock`; Composer does not re-resolve a path package's `require` on `install`, so a change to either package's `composer.json` must be followed by `composer update stats4sd/odk-link-core stats4sd/odk-link-filament` in the app and a lock commit, or reference CI tests the old transitive set. The same goes for the Filament package's own lock after a core `composer.json` change (`composer update stats4sd/odk-link-core` in `packages/odk-link-filament`).
- The reference app holds **published copies** of the package config and migrations (`apps/reference/config/odk-link.php`, `apps/reference/database/migrations/2026_09_08_*`). The package never calls `runsMigrations()`, so publishing is the only way a host gets the schema. When the core adds a migration, re-publish in the app and commit; re-publishing keeps existing timestamps and appends new files, so a migration that must run mid-sequence needs a manual rename. Procedure in the app README.
- Package issues surfaced by the reference app, deferred beyond Step 4 (list with detail in `apps/reference/README.md`, "Known package issues"): storage config reads the misspelled key `filesystem.default`; `PlatformSeeder` skips the `Platform` row the admin resource needs when `ODK_URL` is empty; template view and edit pages read `rootSection->schema` and a `bool` closure returns `null`, so they cannot render for a template that has not been imported; saving a template without a file calls ODK Central through `getRequiredMedia()`; `HasXlsforms::country()` reads `owner_id` on the owner table; the submission update route has no middleware; three migrations (`016`, `018`, `027`) pass on SQLite but fail on MySQL (unindexed FK target, `nulllable()` typo, `default(0)` on an FK column). One package edit was made in step 3: the `XlsformTemplateInfolist` import in `XlsformTemplateResource` was case-corrected to `XlsformTemplateInfoList` because it fataled on Linux.
- There is no `OdkLinkTeam` plugin in either package. The reference app's `team` panel has tenancy wired but nothing from the packages in it. Team-panel stubs are out of scope for step 7; see the step 7 plan for what exists.
- Since step 6 the packages have separate namespaces (`Stats4sd\OdkLink\Core\`, `Stats4sd\OdkLink\Filament\`). Both `ShippingBoundary` arch tests forbid the old `Stats4sd\FilamentOdkLink\` prefix; the only allowed occurrence is the core's `035_rename_odk_link_class_names` migration.
- Polymorphic `*_type` columns store full class names; the core registers no morph map. Any future rename of a core model's namespace needs another class-name migration like `035`. Adding a morph map first needs the raw `::class` writes and queries on morph columns (`XlsformTemplateLanguageStringImport`, `XlsformModuleVersion`) switched to `getMorphClass()`.
- `packages/odk-link-core/UPGRADING.md` collects breaking changes for the step 8 migration guide. Add to it in the same PR as any breaking change.
- `OdkLinkFilamentServiceProvider` calls `$this->app->register(OdkLinkCoreServiceProvider::class)` before binding its adapters. That makes the adapter bindings win regardless of package discovery order; `tests/Ordering` in the Filament package covers the core-first order.
- `.env` files cannot be read or written by the agent tooling in this repo (permission deny rule). The app README documents the `.env` lines a developer adds by hand; `.env.example` is the untouched skeleton copy.
