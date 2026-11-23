# Restructuring Step 6: package, namespace and config renames

**Status:** In Progress

Names confirmed as written on 2026-11-23. Commit 1 (step 5 review fixes, plans, roadmap) done; commits 2–5 remain.

## Context

The roadmap in `RESTRUCTURING.md` used to put the renames into the release step (old step 7), after the stub conversion. The step 5 review (2026-11-23) moved them forward. Once the Filament UI is published into host apps as stubs, every published file embeds the current names: `use Stats4sd\FilamentOdkLink\...`, `view('filament-odk-link::...')`, `config('filament-odk-link.odk.url')`, plus the translation keys added in step 7. A rename after publishing would break every host copy, including the reference app's. If the names are final first, they get frozen into host code only once. The rename also ends the shared `Stats4sd\FilamentOdkLink\` PSR-4 prefix before step 7 adds new classes.

Decisions taken (2026-11-23):
- The renames are their own PR, ahead of the stub conversion.
- The step 5 doc fixes go into the first commit of this step.
- The UI install command is `odk-link:install-filament` (step 7). The core's spatie install command becomes `odk-link:install` as a result of this step, so the names do not collide.

Branch: `restructure-step-6-renames` off `dev`.

## Name mapping

| Thing | Before | After |
| --- | --- | --- |
| Core Composer name | `stats4sd/filament-odk-link` | `stats4sd/odk-link-core` |
| Filament Composer name | `stats4sd/odk-link-filament` | unchanged |
| Core PHP namespace | `Stats4sd\FilamentOdkLink\` | `Stats4sd\OdkLink\Core\` |
| Filament PHP namespace | `Stats4sd\FilamentOdkLink\` (shared) | `Stats4sd\OdkLink\Filament\` |
| Core test namespace | `Stats4sd\FilamentOdkLink\Tests\` | `Stats4sd\OdkLink\Core\Tests\` |
| Filament test namespace | `Stats4sd\FilamentOdkLink\Filament\Tests\` | `Stats4sd\OdkLink\Filament\Tests\` |
| Seeders / factories | `Stats4sd\FilamentOdkLink\Database\{Seeders,Factories}\` | `Stats4sd\OdkLink\Core\Database\{Seeders,Factories}\` |
| Spatie package name (`OdkLinkCoreServiceProvider::$name`) | `filament-odk-link` | `odk-link` |
| Config file / key | `config/filament-odk-link.php`, `config('filament-odk-link.*')` | `config/odk-link.php`, `config('odk-link.*')` |
| Publish tags | `filament-odk-link-config`, `filament-odk-link-migrations` | `odk-link-config`, `odk-link-migrations` |
| Core install command | `filament-odk-link:install` | `odk-link:install` |
| View namespace and publish tag | `filament-odk-link::`, `filament-odk-link-views` | `odk-link::`, `odk-link-views` |
| Filament asset package / CSS id | `stats4sd/filament-odk-link`, `filament-odk-link-styles` | `stats4sd/odk-link-filament`, `odk-link-styles` |
| Built CSS file | `resources/dist/filament-odk-link.css` | `resources/dist/odk-link.css` |
| Event provider | `FilamentOdkLinkEventServiceProvider` | `OdkLinkEventServiceProvider` |
| Env keys | `ODK_*`, `MAIN_OWNER_TYPE` | unchanged |

Not renamed here:
- the directory names `packages/odk-link-core` and `packages/odk-link-filament`, which are already final;
- the GitHub repo (step 8);
- `OdkLinkAdmin`, which step 7 deletes. Its namespace moves with the package; its plugin id `stats4sd-odk-link` stays.

Deleted rather than renamed:
- the empty `FilamentOdkLink` class and the `FilamentOdkLink` facade in core, together with the `extra.laravel.aliases` entry. Neither has content or callers.

Env keys are kept so that hosts do not have to edit `.env` on deploy. A config rename already forces hosts to republish or rename one file, and that is enough churn for one release.

## Persisted class names (data migration)

The core registers no morph map. Polymorphic `*_type` columns therefore store full class names, and after the namespace rename existing rows would point at classes that no longer exist. Affected columns:
- `media.model_type` (spatie medialibrary; the table is shared with host models)
- `odk_projects.owner_type`
- `entities.model_type`
- `submissions.primary_data_subject_type`
- `language_strings.linked_entry_type` (part of the `unique_language_string` index)
- `xlsform_templates.owner_type`
- `datasets.model_type`
- plus any other `morphs()` columns that a full grep of the migrations turns up

Most of these hold host classes, such as `App\Models\Team` as an owner. Only values that start with `Stats4sd\FilamentOdkLink\` are core classes.

Plan:
1. Core registers a non-enforced `Relation::morphMap()` with short aliases for every core model that can appear on the morph side of a relation (for example `odk-link.xlsform`, `odk-link.xlsform-version`). Non-enforced, so host models keep storing their class names. From then on, future namespace changes do not touch data.
2. A new core migration `0xx_convert_odk_link_morph_types.php` rewrites every value in the affected columns that starts with `Stats4sd\FilamentOdkLink\` to its alias. It uses a fixed old-FQCN → alias table written into the migration, never computed from current class names. `down()` reverses it.
3. Core test: insert rows carrying old FQCNs into each affected column, run the migration's `up()`, assert aliases, and assert that the relation resolves. Rows with host class names are left untouched.
4. The upgrade notes tell hosts to drain the queue before deploying. Serialized jobs and queued Excel exports carry old class names, and nothing can migrate those.

## Implementation

### Commit 1: step 5 review fixes and roadmap
- `RESTRUCTURING.md:85` and `.github/workflows/README.md`, core-ci and filament-ci rows: the PHP 8.5 job has passed since PR #172 (Laravel Excel 4 / PhpSpreadsheet 5). Rewrite the note; making 8.5 a required check moves to step 8.
- Step 5 change log and plan: remove the claim that `staudenmeir/belongs-to-through` is unused. Five core models use `BelongsToThrough`.
- Step 5 change log: record that five unreferenced views moved instead of being deleted (`form-review`, `team-qr-code`, `draft-qr-code`, `team-datasets-required`, `custom-odk-templates-widget`), and that step 7 deletes them.
- `packages/odk-link-filament/.gitattributes`: export-ignore `CLAUDE.md`. `composer.json`: add `"type": "library"`.
- Roadmap reordered (6 renames, 7 stubs, 8 release plumbing), together with this plan and the step 7 plan.

### Commit 2: core rename
- `composer.json`: name, PSR-4 maps, provider FQCN in `extra.laravel.providers`, alias removed.
- Namespace rewrite across `src/`, `database/`, `config/`, `routes/` and `tests/`, by scripted `sed` over `Stats4sd\\FilamentOdkLink\\` → `Stats4sd\\OdkLink\\Core\\`. Escaped forms in strings, such as `ConfiguredContracts` messages and `"Stats4sd\\FilamentOdkLink\\Commands\\{$filename}"` in the provider, need a second pass.
- `git mv config/filament-odk-link.php config/odk-link.php`; `$name = 'odk-link'`; rewrite the 50 `config('filament-odk-link.` calls and the error-message strings that quote config keys.
- `git mv` `FilamentOdkLinkEventServiceProvider` → `OdkLinkEventServiceProvider`; delete the empty class and the facade.
- Morph map and conversion migration, with its test (see above).
- `phpstan-baseline.neon`: rewrite class names in messages.
- `ArchTest` / `ShippingBoundary`: update namespace targets. Add the old prefix `Stats4sd\FilamentOdkLink` to the forbidden list so no stray reference survives.
- `composer update` in the package (lock name change only). Run tests, analyse and Pint.

### Commit 3: Filament package rename
- `composer.json`: `require` and `versions` pin `stats4sd/odk-link-core`, PSR-4 `Stats4sd\\OdkLink\\Filament\\` → `src/`. All source moves up one directory level, because the `Filament/` sub-namespace no longer adds anything (`src/Filament/OdkAdmin/...` → `src/OdkAdmin/...`, `src/Filament/Support` → `src/Support`, and so on). That keeps class names short in the stubs step 7 publishes. Use `git mv`, then the namespace `sed`.
- `OdkLinkAdmin` discovery paths and namespaces follow the move.
- Provider: view namespace `odk-link`, tag `odk-link-views`, asset package `stats4sd/odk-link-filament`, CSS id `odk-link-styles`, built file `odk-link.css`. Update `package.json` build output and the `resources/css/index.css` `@source` paths after the move.
- Rewrite the 11 `filament-odk-link::` view references and the one `config('filament-odk-link.` call.
- Tests, baseline, `ShippingBoundary` (also forbid the old prefix), `composer update stats4sd/odk-link-core`, lock committed. Run tests, analyse and Pint.

### Commit 4: workspace, reference app, CI
- Root `composer.json`: `versions` and `require` key renamed; scripts unchanged; lock updated.
- `apps/reference`:
  - `composer.json` `require`/`versions`;
  - `composer update stats4sd/odk-link-core stats4sd/odk-link-filament` and lock commit;
  - `git mv config/filament-odk-link.php config/odk-link.php` and apply the same key changes as the package config;
  - rewrite the `App\` code that imports package classes (`Team`, `User` contracts, seeders, tests);
  - publish the new morph-conversion migration (`php artisan vendor:publish --tag=odk-link-migrations`) and commit it;
  - README: the tag names in the re-publish procedure.
- `.github/workflows/*`: search for package names and config file references. No job names change, so the required checks are untouched.
- `.github/dependabot.yml`: unaffected (directories unchanged); confirm.
- Root `CLAUDE.md`, both package `CLAUDE.md` and `README.md` files, `.claude/settings.json` (two stale allow-rules that reference the old path; remove them).

### Commit 5: docs
- `packages/odk-link-core/UPGRADING.md`, new. This is the seed of the step 8 migration guide. It lists every row of the name mapping, the config file rename, the publish tag changes, the morph migration, "drain the queue first", and the removal of the facade alias. Step 7 appends its UI changes.
- `RESTRUCTURING.md`: Status line, tick step 6, resolve the "Package and namespace names" and "Config file name" open decisions. Remove the note about the shared PSR-4 prefix and replace it with the morph-map note.
- Plan Status → Completed; change log `docs/change-logs/2026-11-23-restructuring-step-6-renames.md` (dated when done).

## Out of scope
- Stub conversion, translations, dead-code cleanup in the UI (step 7).
- GitHub repo rename, subtree split, Packagist, replacing the `dev-dev` pins, tagging (step 8).
- B12–B15 bug work (step 1).

## Verification
1. `grep -rn 'FilamentOdkLink\|filament-odk-link' --exclude-dir={vendor,node_modules,.git,docs} .` matches only the conversion migration, `UPGRADING.md`, `CHANGELOG.md` history and `RESTRUCTURING.md`.
2. `composer test:all`, `composer analyse:all`, Pint in both packages, `composer validate --strict` in both packages, `composer validate --no-check-publish` at root.
3. Reference app: `composer test` (22 passed, 1 skipped, plus any new tests); `php artisan migrate:fresh --seed`; `php artisan about` lists both providers; `php artisan list odk-link` shows `odk-link:install`.
4. Morph conversion: the core test passes. Manually, on a reference DB migrated and seeded **before** the rename (check out `dev`, `migrate:fresh --seed`, upload one template with media; switch branches, `migrate`), the template and its media still load in `/admin`.
5. CI: `core-ci`, `filament-ci` and `reference-ci` green.
