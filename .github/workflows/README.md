# GitHub workflows

Reference guide for everything under `.github/`. Keep this table current when adding or changing a workflow.

| Workflow | Purpose | Trigger | Notes |
| --- | --- | --- | --- |
| `core-ci.yml` | Tests, PHPStan, Pint check and `composer validate` for `packages/odk-link-core` | push to `dev`, PR to `dev`, manual | Jobs: `composer validate`, `tests (PHP 8.4)`, `tests (PHP 8.5)`, `phpstan`, `pint`. `tests (PHP 8.5)` is informational and currently fails at `composer install` because `maatwebsite/excel ^3.1` pins `phpoffice/phpspreadsheet ^1.x`, which declares `php <8.5.0`; it is not a required check. All run inside the package directory because its `post-autoload-dump` (`testbench package:discover`) only fires when the package is the Composer root. Dependencies are resolved with `composer update --prefer-stable` (highest), so the tracked `composer.lock` is ignored; switch `dependency-versions` to `locked` if CI should honour it. Pint is check-only: run `composer format` locally to fix. PHPStan uses `phpstan-baseline.neon`; new errors must be fixed, not re-baselined. |
| `reference-ci.yml` | Tests, Pint check and `composer validate` for `apps/reference` | push to `dev`, PR to `dev`, manual | Jobs: `reference composer validate`, `reference tests (PHP 8.4)`, `reference pint`. Job names carry the `reference` prefix because required status checks match by check-run name across workflows; a job called `pint` here would collide with `core-ci`'s required `pint`. Installs from the committed `apps/reference/composer.lock` (`dependency-versions: locked`) because this is an application, not a library. Caveat: Composer does not re-resolve the path package's `require` on `install`, so after the core's `composer.json` changes, run `composer update stats4sd/filament-odk-link` in `apps/reference` and commit the lock. `composer validate` runs without `--strict`: the path requirement on the core is `@dev`, which `--strict` rejects. No PHP 8.5 job: the app inherits the core's `phpspreadsheet` block and one red job is enough. Not yet in the `dev` ruleset; add `reference tests (PHP 8.4)` once it has run green a few times. |
| `update-changelog.yml` | Prepend release notes to `packages/odk-link-core/CHANGELOG.md` when a GitHub release is published | `release: released` | Commits to `dev`. Uses `release.tag_name` as the version. Assumes every release is the core package; the tag-to-package mapping is revisited in restructure step 7 (subtree split). |
| `dependabot-auto-merge.yml` | Enable auto-merge on Dependabot PRs for minor/patch bumps | `pull_request_target` | Restricted to the `github_actions` ecosystem. Composer bumps need a human review. Repo-level "Allow auto-merge" is currently off, so `gh pr merge --auto` errors and Actions bumps are merged by hand after CI; enable the setting to make this workflow effective. |
| `auto-assign-issues-to-project-board.yaml` | Add every new issue to org project board 10 | `issues: opened` | Needs the `PROJECT_ACCESS_TOKEN` secret (PAT with Projects read/write). If runs fail with `Bad credentials`, the PAT has expired: rotate it in repo settings. |
| `../dependabot.yml` | Dependabot config | weekly | Ecosystems: `github-actions` at `/`, `composer` at `/packages/odk-link-core` and `/apps/reference` (minor and patch grouped). |
| Dependabot Updates (GitHub-managed) | Runs the Dependabot checks | weekly, from `dependabot.yml` | No file in repo. |
| CodeQL (GitHub default setup) | Code scanning | push to `dev`, weekly schedule | No file in repo. PHP is not a CodeQL language, so this scans JS and Actions only. |

## Planned, not yet present

Restructure roadmap steps 5, 6, 7 (see `RESTRUCTURING.md`): `filament-ci.yml`, a published-UI drift check, and `split.yml` for the subtree split to the read-only repos on tag. For a per-package suite copy `core-ci.yml`, change both `defaults.run.working-directory` and `env.PKG`, and prefix the job names so they cannot collide with checks the ruleset already requires.

## Repo settings that the workflows depend on

- Ruleset "Tests Pass" on the default branch (`dev`): requires `tests (PHP 8.4)`, `phpstan` and `pint`, strict up-to-date policy, blocks deletion and force-push. In place since 2026-09-08. Direct pushes to `dev` are rejected; use a PR.
- "Allow auto-merge" (repo setting) is off. Turn it on if Dependabot Actions bumps should merge without a click.
- `PROJECT_ACCESS_TOKEN` secret for the issue auto-assign workflow. Rotated 2026-09-08.
