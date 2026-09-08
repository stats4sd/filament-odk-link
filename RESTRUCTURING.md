# Restructuring: filament-odk-link → odk-link monorepo

**Status**: In Progress (step 2 of 7, see Roadmap) **Branch**: `restructure` (off `dev`) **Started**: 2026-09-08 **Origin**: [docs/code-reviews/2026-07-06-architecture-package-vs-app.md](docs/code-reviews/2026-07-06-architecture-package-vs-app.md) and its companion [docs/code-reviews/2026-07-06-package-review.md](docs/code-reviews/2026-07-06-package-review.md). Note that `docs/` is gitignored at the root, so those files exist only on the machine where the reviews were run.

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
├── docs/                         plans, change-logs, code-reviews (currently gitignored)
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
| Outstanding bug work | Deferred | The four ⚠️ items in the package review (B12–B15) are independent of packaging. They are parked rather than fixed first. Track them before the split release. |

## Open decisions

- **Package and namespace names.** Working assumption is `stats4sd/odk-link-core` with `Stats4sd\OdkLink\Core` and `stats4sd/odk-link-filament` with `Stats4sd\OdkLink\Filament`. Not yet applied; the core still reads `stats4sd/filament-odk-link` and `Stats4sd\FilamentOdkLink`.
- **Config file name.** `filament-odk-link.php` → `odk-link.php`, and whether env keys keep the `ODK_` prefix.
- **Drift check for published UI.** Cheapest option is a CI job that runs the install command into a scratch app and diffs against `apps/reference`. Decide before step 6.
- **What stays in the Filament package at runtime.** Pure stubs plus an install command, or a thin plugin class that registers published resources. Decide at step 6.
- **GitHub repo rename.** `filament-odk-link` → `odk-link`. GitHub redirects the old URL. Do it when the split repos are created so the names line up.
- **Which host apps consume this.** Unknown count. Affects how much ceremony the migration guide needs.

## Roadmap

Each step is its own PR with the core test suite green.

- [ ] **1. Close out the remaining ⚠️ items from the package review.** Deferred by decision, not done. Track them as issues before the split release.
- [ ] **2. Mechanical move into `packages/odk-link-core`.** In progress.
  - [x] `git mv` the whole package into `packages/odk-link-core/` (all files renamed, history preserved). `stubs/.gitkeep`, `test.txt`, `bin/build.js` and `.phpunit.cache` were deleted rather than moved.
  - [x] Root `composer.json` written and validated. Path repo resolves the core as `dev-restructure` in a dry run.
  - [x] Run the core suite from its new location and confirm green: `composer test` at root, or `composer test` inside `packages/odk-link-core`.
  - [x] Fix `.github/workflows/*`. They still reference root `composer.json` and `vendor/bin/pest`. They are also stale independent of the move: `run-tests.yml` targets branch `main`, PHP 8.1/8.2, Laravel 10, while the package targets PHP 8.4, Laravel 13 and merges to `dev`. Rewrite rather than patch. Plan: [docs/plans/2026-09-08-github-workflows-monorepo.md](docs/plans/2026-09-08-github-workflows-monorepo.md) (local only, `docs/` is gitignored).
  - [x] Add a root `CLAUDE.md` pointing agents at the package-level `packages/odk-link-core/CLAUDE.md` and this file, and repair the `@.claude/...` includes in the package `CLAUDE.md` (the `.claude/` directory stayed at root). The untracked root `AGENTS.md` from before the move is gone.
  - [x] Commit, PR to `dev`.
- [ ] **3. Add `apps/reference`.** Fresh Laravel + Filament install with a `Team` model, requiring core by path. Gives a manual test surface immediately.
- [ ] **4. Purge host-app coupling behind contracts.** Replace `App\Models\*` in `Dataset`, `ImportAllLanguageStrings`, the two `NotifyUserThat*` jobs and `HelperService`. Replace the `ClassFinder` scan and `Role::findByName`. Define `FormOwner`, `PlatformUser`, `SubmissionProcessor`, `RoleResolver` contracts bound via config. Add arch tests forbidding `App\` and `Filament\` imports in core.
- [ ] **5. Extract `packages/odk-link-filament`.** `git mv` `src/Filament`, `resources/views`, `resources/dist`, the two plugin classes and the asset build config. Drop `filament/filament` from core's `composer.json`. PHPStan on core without Filament installed is the enforcer.
- [ ] **6. Convert the Filament package to stubs plus an install command.** `odk-link:install --panel=...`. Add the translation layer at the same time. Wire `apps/reference` to consume the published output and add the drift check.
- [ ] **7. Release plumbing.** Subtree split workflow to read-only repos, register both on Packagist, rename the GitHub repo, write the migration guide, tag.

## Notes for whoever picks this up

- The move happened on branch `restructure`, merged to `dev` as PR #149 on 2026-09-08 (roadmap step 2 complete).
- Root `.gitignore` patterns are unanchored (`vendor`, `node_modules`, `docs`), so they already cover the nested package directories. `composer.lock` is no longer ignored anywhere.
- The core's `post-autoload-dump` runs `testbench package:discover`. That only fires when the core is the Composer root, which is why tests must run inside `packages/odk-link-core` and not against the root vendor.
- Testbench stays for the core even after the reference app exists. The reference app is for the UI layer and manual checks, not a replacement for isolated package tests.
- Project working patterns (plans in `docs/plans/`, change logs in `docs/change-logs/`, status lines) are in `.claude/` at the root, which did not move with the package. `CLAUDE.md` did move, so the `@.claude/...` includes at the top of `packages/odk-link-core/CLAUDE.md` now point at paths that do not exist relative to that file. Fix as part of the root `CLAUDE.md` task in step 2. When steps 3 onward begin, write a plan file per step.
- CI lives in `.github/workflows/core-ci.yml` and runs inside `packages/odk-link-core`. [.github/workflows/README.md](.github/workflows/README.md) is the tracked reference for every workflow and the repo settings they rely on. Per-package suites for later steps are a copy of `core-ci.yml` with a different working directory.
- `core-ci` is green on `dev` except `tests (PHP 8.5)`, which fails at `composer install`, not on package code: `maatwebsite/excel ^3.1` requires `phpoffice/phpspreadsheet ^1.x`, and 1.30.x declares `php <8.5.0`. The 8.5 job is deliberately kept in the matrix as an early warning and is not a required check. It will go green on its own once `maatwebsite/excel` accepts a PhpSpreadsheet release that supports 8.5, or when the Excel dependency is replaced. Do not add `continue-on-error`; a visible red is the point.
- The `dev` ruleset ("Tests Pass") requires `tests (PHP 8.4)`, `phpstan` and `pint`, with the strict up-to-date policy: a PR must be rebased or merged with `dev` before it can merge, so expect to re-run CI after any other PR lands. Direct pushes to `dev` are blocked by the same rule; every change goes through a PR, including doc-only edits like this one.
- Repo-level "Allow auto-merge" is off. Dependabot's `gh pr merge --auto` therefore errors out rather than merging; Actions bumps still need a manual merge click after CI. Enable it in repo settings if hands-off Actions bumps are wanted; the ruleset now provides the CI gate that was missing.
- `PROJECT_ACCESS_TOKEN` was rotated on 2026-09-08 and the issue auto-assign workflow succeeds again. If it starts failing with `Bad credentials`, the PAT has expired: rotate, do not debug.
- Step 3 onward: when a second package gets a test suite, add a `filament-ci.yml` by copying `core-ci.yml` and changing `defaults.run.working-directory` and `env.PKG`. Add its `tests (PHP 8.4)` context to the ruleset; the check names are per-workflow, so the new job will not be required until someone adds it.
