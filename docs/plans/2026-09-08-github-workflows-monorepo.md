# GitHub workflows: repoint for the monorepo

**Status**: Complete. Merged to `dev` in PR #149 (2026-09-08). Step 7 done: PAT rotated, `dev` ruleset "Tests Pass" active. `tests (PHP 8.5)` fails at composer install (phpspreadsheet 1.x declares php <8.5); kept as informational. **Branch**: `restructure`. **Parent**: [RESTRUCTURING.md](../../RESTRUCTURING.md), roadmap step 2, bullet "Fix `.github/workflows/*`".

### Progress Log

- 2026-09-08: Review of all workflows completed; plan written.
- 2026-09-08: Steps 1-2 landed (Pint applied, baseline regenerated, CLAUDE.md note). Steps 3-6 landed: `core-ci.yml` added, three dead workflows deleted, `update-changelog.yml` repointed, Dependabot Composer ecosystem + auto-merge guard, `.github/workflows/README.md`. `actionlint` clean. `dependabot/fetch-metadata` left at v3.1.0 (already latest). Remaining: push, open PR to `dev`, confirm four green jobs; step 7 repo settings.

---

## 1. Workflow reference

This table is the reference guide. Update the "State" column as the plan lands, then copy the table to `.github/workflows/README.md` so it is tracked (the `docs/` directory is gitignored).

| Workflow | Purpose | Trigger | State today | Planned |
| --- | --- | --- | --- | --- |
| `run-tests.yml` | Run the Pest suite across a PHP/Laravel matrix | push and PR to `main` | **Dead.** No `main` branch exists (default is `dev`), so it has never run since the switch to `dev`. Matrix is PHP 8.1/8.2, Laravel 10, Testbench 8, Windows + Ubuntu, prefer-lowest. Runs `composer`/`vendor/bin/pest` at repo root. | Replaced by `core-ci.yml` job `tests` |
| `phpstan.yml` | Static analysis with Larastan | push and PR to `main`, paths `**.php`, `phpstan.neon.dist` | **Dead** (same `main` problem). PHP 8.1 fails the `php ^8.4` platform check. Paths filter names a root file that no longer exists. Runs at root, where there is no `phpstan.neon.dist`. | Replaced by `core-ci.yml` job `phpstan` |
| `fix-php-code-styling.yml` | Auto-format with Pint and commit the result | push and PR to `main`, paths `**.php`, `phpstan.neon.dist` | **Dead** (same). Uses `aglipanci/laravel-pint-action` at root; that action has no working-directory input. `ref: github.head_ref` is empty on push events. Cannot push to fork PRs. | Replaced by `core-ci.yml` job `pint` (check only, no auto-commit) |
| `update-changelog.yml` | Prepend release notes to `CHANGELOG.md` when a GitHub release is published | `release: released` | **Broken.** Checks out and commits to `main`. Targets root `CHANGELOG.md`, which moved to `packages/odk-link-core/CHANGELOG.md`. Uses `release.name` rather than `tag_name`. | Rewrite: branch `dev`, package changelog path, `tag_name` |
| `dependabot-auto-merge.yml` | Enable auto-merge on Dependabot PRs for minor/patch bumps | `pull_request_target` | **Works** (recent runs skipped correctly for human PRs). Two risks: `dev` has no branch protection or ruleset, so `gh pr merge --auto` merges immediately with no CI gate. It is not restricted to the `github_actions` ecosystem, so adding Composer to Dependabot would auto-merge library bumps untested. | Keep. Add ecosystem guard. Add a `dev` ruleset requiring the CI checks (repo settings, not code). |
| `auto-assign-issues-to-project-board.yaml` | Add every new issue to org project board 10 | `issues: opened` | **Failing** on every issue: `Bad credentials`. Secret `PROJECT_ACCESS_TOKEN` was set 2025-03-19 and has expired or been revoked. 15 failures today alone. | No code change. Rotate the PAT (repo settings). |
| `.github/dependabot.yml` | Dependabot config | weekly | Only `github-actions` ecosystem, directory `/`. | Add `composer` ecosystem for `/packages/odk-link-core` |
| Dependabot Updates (GitHub-managed) | Runs the Dependabot checks | weekly, from `dependabot.yml` | Works. | Unchanged |
| CodeQL (GitHub default setup, no file in repo) | Code scanning | push to `dev`, weekly schedule | Works. PHP is not a CodeQL language, so this scans JS and Actions only. | Unchanged |
| `core-ci.yml` (new) | Tests, PHPStan, Pint check and `composer validate` for `packages/odk-link-core` | push to `dev`, PR to `dev`, manual | Does not exist | Add |
| `.github/workflows/README.md` (new) | Tracked copy of this table | n/a | Does not exist | Add |

Not in scope now but the layout should leave room for them (RESTRUCTURING.md steps 3, 5, 6, 7): `filament-ci.yml`, `reference-app-ci.yml`, a published-UI drift check, and `split.yml` for the subtree split to the read-only repos on tag.

## 2. Pre-conditions the new CI would fail on today

Verified locally inside `packages/odk-link-core` on this branch:

| Check | Result | Consequence |
| --- | --- | --- |
| `vendor/bin/pest --ci` | 185 passed, 7s | Fine |
| `vendor/bin/phpstan analyse` | **136 errors**, 7s | A PHPStan job goes red immediately |
| `phpstan-baseline.neon` | **Empty file (0 lines)** | The baseline include does nothing. 3 of the 136 are `ignore.unmatchedLine` for the `ColumnDefinition::constrained()` pattern in `phpstan.neon.dist`, which no longer matches anything. |
| `vendor/bin/pint --test` | **Fails on ~150 files** | A Pint check goes red immediately; a Pint auto-fix would push a very large commit |
| `composer validate --strict` (package) | Valid | Fine |
| `composer validate --strict` (root) | Warning: `stats4sd/filament-odk-link: @dev` unbound constraint. `--strict` turns warnings into failures. | Validate root without `--strict` |

PHPStan identifiers by count: `property.notFound` 32, `generics.notSubtype` 22, `return.type` 16, `method.notFound` 11, `larastan.noModelMake` 6, `class.notFound` 4, `class.nameCase` 4, `nullsafe.neverNull` 4, `trait.unused` 4. Heaviest files: `IsXlsformTemplate.php` (18), `XlsformTemplate.php` (15), `GenerateSubmissions.php` (10), `Dataset.php` (8), `Submission.php` (7).

## 3. Decisions

| Decision | Recommendation | Why |
| --- | --- | --- |
| One workflow file or three for the core package | **One `core-ci.yml` with jobs `validate`, `tests`, `phpstan`, `pint`** | Triggers, PHP setup and `working-directory` are declared once. Adding `filament-ci.yml` later is a copy with a different directory. Each job still appears as its own status check. |
| Pint: auto-fix commit or check | **Check (`pint --test`). Delete the auto-fix workflow.** Run `composer format` once locally as its own commit before enabling. | Auto-commit to PR branches makes PRs moving targets, cannot push to forks, and the action has no working-directory input. A failing check is explicit and the fix is one local command. Alternative if auto-fix is wanted: run it only on `push` to `dev`, never on PRs. |
| PHPStan: 136 errors | **Regenerate the baseline, remove the stale `ignoreErrors` pattern, enable the job.** Fix errors down over time; step 4 (contracts) touches most of the heavy files anyway. | Green CI with a ratchet beats a red job everyone ignores or a `continue-on-error` that nobody reads. Fixing 136 errors first blocks the whole restructure. |
| Dependency versions in CI | **`composer update --prefer-stable` (highest), not the lock file.** | This is a library. CI should test what a consumer installs today. RESTRUCTURING.md already flags the package lock as possibly not staying tracked. |
| Matrix | **PHP 8.4 and 8.5, Ubuntu only, `fail-fast: false`. Drop Windows, Laravel matrix, prefer-lowest, Carbon pin.** | `composer.json` declares `php ^8.4` and `illuminate/contracts ^13.0`, so there is one Laravel line. Nobody develops on Windows and path-repo symlinks are awkward there. prefer-lowest doubles CI time for little value on a package with `^` constraints at current majors. |
| PHP extensions | **`dom, curl, libxml, mbstring, zip, pdo, sqlite, pdo_sqlite, bcmath, intl, gd, exif, fileinfo`** | Drops `imagick`, `soap`, `pcntl`, `iconv` from the old list. `imagick` is slow to build and QR generation falls back to GD. |
| Paths filters | **None for now.** | Whole run is well under 3 minutes. Paths filters combined with required checks leave PRs stuck on "expected" checks that never report. Revisit with `dorny/paths-filter` when a second package suite exists. |
| Trigger branches | `push: [dev]`, `pull_request: [dev]`, `workflow_dispatch` | Matches actual flow. Feature branches get checked via PR. Also add `concurrency` with cancel-in-progress per ref. |
| Changelog and future per-package tags | Point at `packages/odk-link-core/CHANGELOG.md` now. Tag scheme for the split (`core-v1.2.3` vs `v1.2.3`) is a step 7 decision; the workflow will need a tag-prefix to path mapping then. | Do not design step 7 here. |
| Dependabot Composer | Add `composer` ecosystem for `/packages/odk-link-core`, weekly, grouped minor+patch. Guard auto-merge with `steps.metadata.outputs.package-ecosystem == 'github_actions'`. | Library bumps should go through a human until required checks exist on `dev`. |
| Branch protection on `dev` | Add a ruleset requiring `core-ci / tests` and `core-ci / phpstan` once the workflow has run green once. Repo settings, done by a maintainer. | Without it Dependabot auto-merge has no gate and `gh pr merge --auto` merges on the spot. |

## 4. Implementation steps

Each step is a separate commit on `restructure`. Steps 1 and 2 are prerequisites; the workflow will fail without them.

### Step 1: Apply Pint once

```bash
cd packages/odk-link-core && composer format && vendor/bin/pint --test && composer test
```

Commit as `Apply Pint formatting to core package`. Large mechanical diff; review the `fixers` list rather than every line. Watch `no_unused_imports` and `ordered_interfaces`, which are the only fixers in the list that change more than whitespace.

### Step 2: Reset the PHPStan baseline

1. Delete the `ignoreErrors` block from `packages/odk-link-core/phpstan.neon.dist` (the `constrained()` pattern is unmatched).
2. `vendor/bin/phpstan analyse --generate-baseline` in the package directory.
3. Confirm `vendor/bin/phpstan analyse` exits 0.

Commit as `Regenerate PHPStan baseline after package move`. Add a one-line note in `packages/odk-link-core/CLAUDE.md` under Commands that new PHPStan errors must be fixed, not re-baselined.

### Step 3: Add `core-ci.yml`, delete the three dead workflows

Delete `run-tests.yml`, `phpstan.yml`, `fix-php-code-styling.yml`. Add:

```yaml
name: core-ci

on:
  push:
    branches: [dev]
  pull_request:
    branches: [dev]
  workflow_dispatch:

concurrency:
  group: ${{ github.workflow }}-${{ github.ref }}
  cancel-in-progress: true

defaults:
  run:
    working-directory: packages/odk-link-core

env:
  PKG: packages/odk-link-core

jobs:
  validate:
    name: composer validate
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v7
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: none
      - name: Validate root workspace
        working-directory: .
        run: composer validate --no-check-publish
      - name: Validate core package
        run: composer validate --strict

  tests:
    name: tests (PHP ${{ matrix.php }})
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        php: ['8.4', '8.5']
    steps:
      - uses: actions/checkout@v7
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: dom, curl, libxml, mbstring, zip, pdo, sqlite, pdo_sqlite, bcmath, intl, gd, exif, fileinfo
          coverage: none
      - name: Setup problem matchers
        run: |
          echo "::add-matcher::${{ runner.tool_cache }}/php.json"
          echo "::add-matcher::${{ runner.tool_cache }}/phpunit.json"
      - uses: ramsey/composer-install@v4
        with:
          working-directory: ${{ env.PKG }}
          dependency-versions: highest
          composer-options: --prefer-stable --prefer-dist
      - run: composer show -D
      - run: vendor/bin/pest --ci

  phpstan:
    name: phpstan
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v7
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: none
      - uses: ramsey/composer-install@v4
        with:
          working-directory: ${{ env.PKG }}
          dependency-versions: highest
          composer-options: --prefer-stable --prefer-dist
      - run: vendor/bin/phpstan analyse --error-format=github --no-progress

  pint:
    name: pint
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v7
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: none
      - uses: ramsey/composer-install@v4
        with:
          working-directory: ${{ env.PKG }}
          dependency-versions: highest
          composer-options: --prefer-stable --prefer-dist
      - run: vendor/bin/pint --test
```

Notes:

- `ramsey/composer-install` caches per `working-directory`, so the three installs share a cache after the first job.
- The package's `post-autoload-dump` runs `testbench package:discover`; it fires because the package is the Composer root inside `working-directory`. This is why the jobs must not run at the repo root.
- `dependency-versions: highest` runs `composer update`, so the tracked `composer.lock` in the package is ignored by CI. If the lock is kept tracked and CI should honour it, switch to `locked`.
- If PHP 8.5 fails on a dependency rather than on package code, drop it from the matrix and note why in the README table.

### Step 4: Rewrite `update-changelog.yml`

- `ref: dev` on checkout, `branch: dev` on commit.
- `latest-version: ${{ github.event.release.tag_name }}`.
- `path-to-changelog: packages/odk-link-core/CHANGELOG.md` on the updater; `file_pattern: packages/odk-link-core/CHANGELOG.md` on the commit step.
- Keep `stefanzweifel/changelog-updater-action@v1` and `git-auto-commit-action@v7`.
- Add a comment that the tag-to-package mapping is revisited in restructure step 7.

### Step 5: Dependabot

`dependabot.yml`: add

```yaml
  - package-ecosystem: "composer"
    directory: "/packages/odk-link-core"
    schedule:
      interval: "weekly"
    labels:
      - "dependencies"
    groups:
      composer-minor-patch:
        update-types: ["minor", "patch"]
```

`dependabot-auto-merge.yml`: change the two `if:` conditions to also require `steps.metadata.outputs.package-ecosystem == 'github_actions'`. Bump `dependabot/fetch-metadata` if Dependabot has a newer major queued.

### Step 6: Tracked reference guide

Create `.github/workflows/README.md` from the table in section 1 with the "State today" column removed and "Planned" renamed to "Notes". Add a line in `RESTRUCTURING.md` "Notes for whoever picks this up" pointing at it.

### Step 7: Repo settings (maintainer, outside the code)

1. Rotate `PROJECT_ACCESS_TOKEN`: fine-grained PAT with organisation Projects read/write, or classic PAT with `project` scope. Re-run one failed `Auto Assign` run to confirm.
2. After `core-ci` is green on `dev`: add a ruleset on `dev` requiring `tests (PHP 8.4)`, `phpstan`, `pint`, and enable "Allow auto-merge" so Dependabot's `--auto` waits for those checks.

## 5. Verification

- `actionlint .github/workflows/*.yml` if installed (`brew install actionlint`); otherwise rely on the first push.
- Push `restructure`, open the step 2 PR against `dev`. Expect: `core-ci` runs four jobs, all green; `Dependabot Auto-Merge` shows skipped; no run of the deleted workflows.
- `gh run list --workflow core-ci.yml` shows the run; `gh run view <id>` shows `composer show -D` listing `laravel/framework v13.x` and `orchestra/testbench v11.x`.
- After a maintainer rotates the PAT: open a throwaway issue, confirm `Auto Assign` succeeds, close it.
- `update-changelog.yml` cannot be tested without a release. Validate syntax only; the first real release after the split exercises it.

## 6. Risks

- **Step 1 diff size.** ~150 files. Merging `dev` into `restructure` after this will conflict on any file touched by both. Do step 1 immediately before the PR and merge promptly.
- **Baseline hides real bugs.** 32 `property.notFound` and 11 `method.notFound` are the kind of error that is a runtime fatal. The baseline is a debt register, not a fix. Cross-reference against the ⚠️ items in the package review when step 1 of the roadmap is picked up.
- **PHP 8.5 in the matrix** may fail on a transitive dependency. Treat as informational for the first run.
