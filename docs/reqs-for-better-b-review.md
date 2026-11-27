# What a better part B needs

Reviewed and extended 2026-11-27, after part B of [plans/2026-11-27-package-review-and-redesign.md](plans/2026-11-27-package-review-and-redesign.md) ran on a machine without most of this. What B produced under those limits, and what it could not check, is in part 4 of [code-reviews/2026-11-27-package-structure-review.md](code-reviews/2026-11-27-package-structure-review.md). "Checked" below means checked on that machine on 2026-11-27.

**Blocking the end-to-end scenario (B3)**

- **Container runtime.** Neither Docker nor Podman is installed (checked). A real ODK Central (its own compose stack: Postgres, Enketo, pyxform, nginx) can't be started, so B3 fell back to faked HTTP responses.
- **A reachable ODK Central with throwaway credentials.** Either the local stack above or a disposable test project on a hosted Central. It needs a platform account that can create projects, since creating a `Team` creates a Central project.
  - Use the same Central version as the Groundswell staging server, and record it. API behaviour differs between versions, and several findings rest on documented behaviour only.
  - A local Central normally expects a domain and TLS. Settle beforehand how the app reaches it (self-signed certificate that PHP is told to trust, or plain HTTP behind a local proxy); check Central's own setup docs for the supported options.
  - For the Enketo edit step, Central must be able to redirect the browser back to the reference app, so the app needs a URL the browser can reach.
- **A way to give the app its ODK settings.** A permission rule stops me reading or writing `.env` files, `.env.testing` included. I can't set `ODK_URL`, `ODK_USERNAME`, `ODK_PASSWORD`, `ODK_PLATFORM_PROJECT_ID`, `APP_TIMEZONE`, or the database and queue settings for `apps/reference`. Options: you create `apps/reference/.env` by hand before the run, or allow a dedicated file such as `.env.e2e` that I may write.
- **MySQL for the reference app.** A server is listening on `127.0.0.1:3306` (checked), but I have no credentials: `mysqladmin ping` is refused. I need a database I may drop and recreate, and a user for it. It matters because:
  - The XLSForm workbook export uses `json_keys`, which is MySQL-only, so form generation can't run on SQLite at all.
  - Migrations `016`, `018` and `027` pass on SQLite and fail on MySQL.
  - Unique indexes with NULLs and JSON queries are where several hotfixes came from; SQLite tests can hide those.
  - Foreign-key behaviour on delete differs (review U7, P17).
- **A schema that can deploy at all.** On a fresh database the core cannot complete a draft deploy: code uses columns no core migration creates (review F9, P7). To run the scenario before the redesign fixes that, I need either permission to add a throwaway migration in the reference app, or the Groundswell app's migration copies to borrow from.
- **A queue worker and scheduler that can run.** Import, build, deploy and polling are all queued chains. With `QUEUE_CONNECTION=database` I need to be allowed to run `php artisan queue:work` and `schedule:run` in the reference app for the duration of a scenario. More than one worker is needed to reproduce the races (choice-list import vs workbook import, duplicate deploys, overlapping polls).
- **Permission to drive the app from the command line.** The post-publish half has no UI in this repo: publish, poll, export and submission edit can only be reached through `php artisan tinker` or small scripts. I need to be allowed to run those against the reference app.

**Making results more realistic**

- **A way to submit data to Central without a phone.** Either Central's REST submission endpoint with prepared XML instances, or Enketo in the local stack. Also needed: editing and deleting a submission on Central, rejecting one (review state), and attaching media, including attaching it *after* the submission to reproduce the late-attachment case.
- **A way to make Central misbehave on demand.** Several predictions are about failure handling. Needed: stop and start the Central containers mid-request; a proxy in front of Central that can return 503 or add latency; and the ability to invalidate the platform session (`DELETE /v1/sessions/<token>` or a password change) to test token recovery.
- **A script that loads volume.** About 5,000 submissions on one form, to test whether the second poll is cheap (review P25). Needs the submission endpoint above and an agreed scale target (target-design decision D24).
- **Realistic fixtures.** The actual Groundswell template workbook(s), a filled-in team translation file, and a farm/entity import file. The repo's `valid-form.xlsx` is too small to hit the same-named-list and multi-module cases. The app repo has `replace-xlsform-v1/v2.xlsx`, which would be a good start.
  - For the post-publish scenario the template must also contain: an integer whose valid answer is 0, a `select_multiple` with a list, a `select_multiple … or_other`, a `select_one_from_file` backed by a dataset, a geopoint, an image, and a repeat with a nested repeat. If no real template has all of these I will build one; say if that is acceptable.
  - Invalid workbooks for the admin UI: no `survey` sheet, an empty sheet, an untagged language column, a language the platform does not know, a non-xlsx file.
- **A local checkout of `groundswell_platform`.** I read it through `gh` one file at a time, which is slow and can't run its tests. A checkout (with its submodule) lets me run the app's own suite, which contains package-behaviour tests (`ReplaceXlsformKeepsTeamVersionsTest`, `DeployJobGuardsTest`, `TranslationExportScopeTest`).
- **An anonymised copy of the staging database, if possible.** It would show the real data shapes (duplicate entries, emptied versions, stuck counters) rather than what the code implies. It would also settle how often same-named lists and NULL cascade filters actually occur, which bears on target-design decisions D6 and D7.
- **A browser I can drive against the reference app.** The Filament UI has almost no tests. Clicking through as a non-expert admin (upload, replace, edit a list) would find the off-path problems that reading code only predicts. That needs the app served locally (`php artisan serve`, or Valet, which is installed) and a seeded admin login.
  - Two browser sessions at once, to test two admins replacing a template concurrently (review U4).
  - More than one seeded user: a platform admin, a member of team A, a member of team B, and one with no rights. Today the reference app has one user who can do everything, so authorisation and cross-team leaks (review U6, U10, P3) cannot be exercised.
  - Database notifications switched on in the reference admin panel. Failure notifications go to the database, and the panel does not enable them, so queued failures are invisible in the UI today.
- **Two teams with data in the reference app.** A second team, a private choice entry and a removed entry for one of them, and one shared lookup dataset with a record from each team. Without this the owner-leak cases have nothing to leak.
- **Recording what the real Central returns.** While the real run happens, I should save the actual responses (submission list, OData feed with a geopoint and a repeat, attachment list for a partly uploaded submission, error bodies) as fixtures. The faked scenario then stops depending on hand-written payloads. Say if any of it must not be committed.
- **Optional: an S3-compatible disk (for example MinIO).** The media code assumes local paths (review P24). Only worth it if any host stores media on S3.

**What I will check first when this is available**

In this order, because the first three change the redesign's priorities:

1. What the app-user token in the QR code can do (review P1): `GET /v1/key/<token>/projects/<id>/forms/<form>/submissions` must return 403. If it returns data, this is a live security problem on Groundswell staging, not a design note.
2. The shape Central really returns for geopoints, late attachments and the attachment list (review P11, P12). These are marked *(inferred)* in the review.
3. A full run on a fresh MySQL database: migrate, upload, deploy, publish (review F9, P7).
4. The 28-step runbook in part 4 of the review, recording actual against predicted.
5. The form-definition hotfix scenarios from the ledger against the real import pipeline: re-import the same workbook twice and compare ids (F1), re-import with a team variant present (F2), two workers (F15).
6. The admin UI click-through.

Output of that update: part 4 of the review rewritten with real results, *(inferred)* labels removed or the finding withdrawn, new ledger rows for anything found, and the skipped steps in `packages/odk-link-core/tests/Unit/Scenarios/` extended with the recorded fixtures.

**Housekeeping that affects accuracy**

- **The part B work must be committed and pushed before changing environment.** As of 2026-11-27 it exists only as uncommitted files on local branch `package-review-and-redesign` (the review, ledger, target design, plan, this file, the scenario test, and edits to `RESTRUCTURING.md` and the step 7 plan). A new environment that clones the repo will not have it. I have not committed because I was not asked to.
- **Fresh `vendor/` installs per package.** The root `CLAUDE.md` warns that a long-lived `vendor/` hides failures (missing `APP_KEY`, different Larastan inference). Permission to run `composer install` in a clean clone or worktree avoids false passes. Composer 2.11 is present (checked).
- **PHP extensions.** PHP 8.4 is the default and `php8.5` is also installed (checked). Loaded: `pdo_mysql`, `pdo_sqlite`, `zip`, `gd`, `intl`, `mbstring`, `bcmath`, `pcntl` (checked). Not loaded: `imagick`, and no coverage driver (`pcov` or `xdebug`), so `composer test-coverage` cannot run. Coverage would be useful for showing which import and ingestion paths the suites never reach.
- **Local branches up to date.** Local `dev` and `staging-hotfix-20261026` were behind `origin` at the start, and the working tree was switched to the hotfix branch mid-session. A note of which branch is safe for me to switch on, or permission to use git worktrees, avoids disturbing your checkout. There is one worktree today, the main checkout (checked).
- **`Hot_Fix_List.xlsx` is superseded by the ledger.** It was already two package PRs and three app PRs behind. [plans/2026-11-27-hotfix-requirements-ledger.md](plans/2026-11-27-hotfix-requirements-ledger.md) now covers every package commit to `b436265` and app PRs #68–#88. New hotfixes should add a ledger row; the spreadsheet can stop being maintained. `openpyxl` isn't installed either; I parsed the file by hand.
- **Node is present** (v24, npm 11; checked), so the Filament package's CSS can be rebuilt during step 7 work.
- **GitHub access.** `gh` is authenticated and can read `stats4sd/groundswell_platform`. If the new environment is different, it needs the same, or the local checkout above.

**Decisions that would sharpen the next run**

Not environment, but they decide what "correct" means in several scenario steps. From section 9 of [plans/2026-11-27-target-design.md](plans/2026-11-27-target-design.md):

- D8: is SQLite a supported production database, or test-only?
- D12: what should happen on the platform when a submission is deleted or rejected on Central?
- D20: who edits submissions, and is the Enketo return route wanted at all?
- D21: one shared app user per owner, or per form or per enumerator?
- D24: the scale to design for.
