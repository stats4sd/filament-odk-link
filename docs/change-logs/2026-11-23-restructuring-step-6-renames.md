# Restructuring step 6: package, namespace and config renames

Implements [docs/plans/2026-11-23-restructuring-step-6-renames.md](../plans/2026-11-23-restructuring-step-6-renames.md), roadmap step 6 in [RESTRUCTURING.md](../../RESTRUCTURING.md). Branch `restructure-step-6-renames`.

## Outcome

Every pre-split name is gone from shipping code. The two packages no longer share a PSR-4 prefix. Step 7 publishes stubs that embed the final names.

| Thing | Now |
| --- | --- |
| Core | `stats4sd/odk-link-core`, `Stats4sd\OdkLink\Core\` |
| UI | `stats4sd/odk-link-filament`, `Stats4sd\OdkLink\Filament\` |
| Config | `config/odk-link.php`, `config('odk-link.*')`; env keys unchanged |
| Tags / command | `odk-link-config`, `odk-link-migrations`, `odk-link-views`, `odk-link:install` |
| Views / assets | `odk-link::`, asset package `stats4sd/odk-link-filament`, stylesheet `odk-link-styles` (`resources/dist/odk-link.css`) |
| Broadcast names | `OdkLink.XlsformTemplateWasImported` and the three siblings |

## Changes

### Commit 1: step 5 review fixes and plans
- Removed the stale "PHP 8.5 fails at install" notes. The 8.5 jobs have passed since PR #172.
- Corrected the step 5 follow-up that called `staudenmeir/belongs-to-through` unused.
- Recorded the five unreferenced views and two unused components that moved in step 5, and the ineffective Blade scan in the Filament arch test.
- Filament package: `"type": "library"`; `CLAUDE.md` is export-ignored.
- Reordered the roadmap (6 renames, 7 stubs, 8 release plumbing) and added the step 6 and step 7 plans.

### Commit 2: core
- Composer name, PSR-4 maps, provider FQCN. Removed the facade alias.
- Scripted namespace rewrite across `src`, `database`, `config`, `routes`, `tests` and the PHPStan baseline, covering singly, doubly and quadruply escaped forms.
- Spatie package name `odk-link`. This renames the config file, both publish tags and the install command in one go. Rewrote 50 `config('filament-odk-link.…')` calls and the error messages that quote config keys.
- `FilamentOdkLinkEventServiceProvider` → `OdkLinkEventServiceProvider`. Deleted the empty `FilamentOdkLink` class and its facade, and dropped the smoke test that resolved the facade.
- `broadcastAs()` names `FilamentOdkLink.*` → `OdkLink.*`.
- New migration `035_rename_odk_link_class_names` with tests (up, down, no-op on a fresh database). Covers seven polymorphic type columns plus `datasets.entity_model`, which stores a class name that is later copied into `entities.model_type`.
- Both arch checks now forbid `Stats4sd\FilamentOdkLink\`. The one allowed occurrence is the migration's literal.

### Commit 3: Filament package
- Flattened `src/Filament/{OdkAdmin,Widgets,Support,Traits,OdkLinkFilamentServiceProvider.php}` into `src/`, using `git mv`, then rewrote namespaces.
- Updated the view namespace, publish tag, asset key, stylesheet id and built CSS file name, plus `package.json` build output paths, PHPStan baseline paths and the Testbench/PHPStan migration paths (`vendor/stats4sd/odk-link-core/...`).
- Fixed the Livewire listener on `ListXlsformTemplates`. It was `#[On('echo.xlsforms,XlsformTemplateWasImported')]`, which is the wrong prefix (`echo.` instead of `echo:`) and misses the custom broadcast name. It is now `echo:xlsforms,.OdkLink.XlsformTemplateWasImported`. Not verified against a running broadcaster.
- Removed two PHPStan baseline entries (`view-string` on both widgets' `$view`) and added `packages/odk-link-filament/testbench.yaml` registering `OdkLinkFilamentServiceProvider`. Larastan treats a string as a `view-string` only if `view()->exists()` is true inside the app it boots through Testbench. On a fresh checkout, the first Larastan boot creates Testbench's `laravel/vendor` symlink and rebuilds the discovery manifest outside the `testbench` CLI, which drops the root package. So the provider never ran, the `odk-link::` namespace did not exist, and the errors appeared (CI and a fresh worktree). A long-lived local `vendor/` already had the symlink, so the manifest written by `post-autoload-dump` (which does include the root package) survived and the views resolved. The baseline entries were suppressing an environment artefact, not a code problem. With the provider declared, analysis is the same everywhere and the entries are unnecessary. The core had the same cause. Without its provider, Larastan cannot read `GenerateSubmissions`' signature, so `$this->argument('xlsform_id')` is `mixed` and `Xlsform::find()` is inferred as `Collection<int, Xlsform>|Xlsform`. Step 5 had widened that baseline entry to match both forms. `packages/odk-link-core/testbench.yaml` now registers `OdkLinkCoreServiceProvider` and the entry is back to the single `Xlsform` form. A no-baseline PHPStan run in a fresh clone, with and without each package's `testbench.yaml`, showed no other environment-dependent errors in either package (core: 103 identical; Filament: only the two `view-string` errors). The first commit of this fix did not include `testbench.yaml`: the root `.gitignore` ignored it everywhere through an unanchored `testbench.yaml` pattern left from the single-package layout, so CI kept failing. That pattern is removed; a fresh clone with `composer install` now passes PHPStan.

### Commit 4: workspace, reference app, docs
- Root and reference `composer.json` and locks; Filament package lock.
- Reference app:
  - `config/filament-odk-link.php` → `config/odk-link.php`;
  - imports in models, the role resolver, the panel provider, seeders, tests and the published migrations;
  - the new class-name migration published as `2026_11_23_170619_035_rename_odk_link_class_names.php`.
- READMEs, `CLAUDE.md` files (root, both packages, and the root `AGENTS.md` mirror), the reference README and the workflows README.
- Kept GitHub URLs and badges pointing at `stats4sd/filament-odk-link`, because the repo is renamed in step 8.
- Removed two stale allow-rules from `.claude/settings.json` that referenced an old local path and `ClassFinder`.
- `package-lock.json` package name `filament-odk-link` → `odk-link-filament`.

### Commit 5: docs
- New `packages/odk-link-core/UPGRADING.md`: the name table, the queue-drain warning, the class-name migration, asset republish and broadcast-name changes. It is the seed of the step 8 migration guide.
- Status updates in `RESTRUCTURING.md` and the plan.

## Deviations from the plan

- **No morph map.** The plan proposed a non-enforced `Relation::morphMap()` with short aliases. Implementing it surfaced raw `::class` writes and queries on morph columns. Examples: `XlsformTemplateLanguageStringImport` writes `'linked_entry_type' => $this->class` and queries by type; `XlsformModuleVersion` filters `linked_entry_type` by `SurveyRow::class`. With a map in place, Eloquent relations would write aliases while these sites wrote full class names, leaving mixed data and broken lookups. The migration therefore rewrites the old prefix to the new one instead. Introducing a morph map later needs those sites switched to `getMorphClass()` first. That is recorded in `RESTRUCTURING.md` notes.
- **Broadcast names and the Echo listener** were not in the plan's mapping; both changed (see above).
- `choice_list_entries` has no morph column, so `ChoiceListEntry::model()` (a `morphTo`) has nothing to read. It is dead code, left as is.

## Verification

| Check | Result |
| --- | --- |
| Core `composer test` | 239 passed (237 − facade test + 3 migration tests) |
| Filament `composer test` | 16 passed |
| Reference `composer test` | 22 passed, 1 skipped (unchanged) |
| `composer analyse:all`, Pint in all three, `composer validate --strict` in both packages, root `--no-check-publish` | pass |
| `php artisan odk-link:install --help` in the reference app | registered (spatie hides it from `list`) |
| Class-name migration on a seeded reference SQLite DB: insert a template owned by `Platform`, roll back `035` (old prefix written), migrate (new prefix), load `->owner` | resolves to `Stats4sd\OdkLink\Core\Models\OdkLink\Platform` |
| `git grep 'FilamentOdkLink\|filament-odk-link'` outside docs, locks, `CHANGELOG.md`, `UPGRADING.md`, the migration and its test, the arch lists, GitHub URLs and the empty `TestsFilamentOdkLink` mixin (deleted in step 7) | no hits |

Not done: rebuilding `resources/dist/odk-link.css`. `node_modules` is not installed, and the rename changes no Tailwind classes, so the existing build was renamed as is.
