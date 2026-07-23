# Code Review — `stats4sd/filament-odk-link`

**Date**: 2026-07-06
**Branch**: `dev`
**Reviewer**: Claude (automated multi-angle review — 5 parallel subsystem passes + manual verification of top findings)
**Scope**: Whole package (168 PHP files, ~13.4k LOC). Reviewed against the package's intended purpose (harmonised, multi-tenant management of ODK XLSForms: admins publish templates/modules, owners derive and deploy their own instances) and `.claude/laravel-php-guidelines.md`.

> The broader "keep it as a package vs. re-architect" question is answered in a companion document: [2026-07-06-architecture-package-vs-app.md](2026-07-06-architecture-package-vs-app.md).

> **Remediation status (branch `pre-publish-fixes`, updated 2026-07-23):** B1–B9, B11 and B14 fixed. Verified with the full Pest suite and PHPStan, no new errors. Per-finding status is noted inline against each item below.

---

## Executive summary

The domain design (Template → Module → ModuleVersion → owner-derived Xlsform, with a single ODK Central gateway service) is sound and the import→deploy→collect pipeline is coherent. But three systemic problems recur across every layer:

1. **Model events do orchestration.** Deploy, publish, import, notification, and cross-table cascades all live in `booted()` hooks and in _attribute accessors_ that perform HTTP calls and DB writes. This makes a plain `->save()` unpredictable and expensive, and is the root of several bugs below.
2. **The "package boundary" is already fictional.** Shipping code hardcodes host-app classes (`App\Models\Project`, `App\Models\Team`, `App\Models\User`), scans the `App\Models` namespace, references `Tests\Models\Team`, and hardcodes a `"Super Admin"` role. This is central to the architecture question.
3. **Leftover debug/test code ships as live artefacts** — including a destructive artisan command and a "TEMP" mutation of form ID 1.

### Highest-priority items (fix before a "stable" release)

| #   | Severity    | Status   | Finding                                                                                                                                                                | Location                                                                                                                                               |
| --- | ----------- | -------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| B1  | 🔴 Critical | ✅ Fixed | `TestRemoveSub` "Specific" option wipes **all** submissions/entities for every tenant and `Cache::flush()`es the host — and this destructive command ships registered  | [TestRemoveSub.php](../../src/Commands/TestRemoveSub.php)                                                                                              |
| B2  | 🔴 Critical | ✅ Fixed | Team users can toggle `available`, edit, replace and **delete Platform-owned templates** for all tenants (destructive admin actions inherited into the team panel)     | [TeamXlsformTemplateResource.php](../../src/Filament/OdkTeam/Resources/TeamXlsformTemplates/TeamXlsformTemplateResource.php)                           |
| B3  | 🔴 High     | ✅ Fixed | Numeric-zero answers in repeat groups are silently dropped (`$value != null`) — data loss                                                                              | [OdkSubmissionService.php:464](../../src/Services/OdkLinkServices/OdkSubmissionService.php#L464)                                                       |
| B4  | 🟠 High     | ✅ Fixed | `ChoiceListEntry` owner global scope is unwrapped — the `orWhereNull` leaks past every later `where`, returning cross-list/cross-owner rows                            | [ChoiceListEntry.php:41](../../src/Models/OdkLink/ChoiceListEntry.php#L41)                                                                             |
| B5  | 🟠 High     | ✅ Fixed | `ChoiceList` relation manager uses Filament **v3** APIs — fatal when rendered                                                                                          | [ChoiceListEntriesRelationManager.php](../../src/Filament/OdkAdmin/Resources/ChoiceListResource/RelationManagers/ChoiceListEntriesRelationManager.php) |
| B6  | 🟠 High     | ✅ Fixed | `UpdateXlsformDrafts` runs `Xlsform::find(1)->update(...)` ("TEMP") on every run — forces a redeploy and fatals if no form has id 1                                    | [UpdateXlsformDrafts.php:29](../../src/Commands/UpdateXlsformDrafts.php#L29)                                                                           |
| B7  | 🟠 High     | ✅ Fixed | `EntityExport` filters on non-existent `dataset_variable_id` — entity exports are blank and fatal on parented datasets                                                 | [EntityExport.php:43](../../src/Exports/EntityExport.php#L43)                                                                                          |
| B8  | 🟠 High     | ✅ Fixed | Broken lookup-list relation: `HasXlsforms::choiceLists()` targets `ChoiceListEntry` but `hasCompletedLookupList()` queries `choice_lists.id` → SQL error on every call | [HasXlsforms.php:81](../../src/Models/OdkLink/Traits/HasXlsforms.php#L81)                                                                              |

Full detail below, in the three sections you requested.

---

# Section 1 — Architectural problems

Ordered by impact.

### 1.1 Model events are used as an orchestration engine

Deploy/publish/import/notification logic is spread across `booted()` hooks in [Xlsform.php:51](../../src/Models/OdkLink/Xlsform.php#L51), [XlsformTemplate.php:44](../../src/Models/OdkLink/XlsformTemplate.php#L44), [Submission.php:45](../../src/Models/OdkLink/Submission.php#L45), `Locale`, and `Entity`. Consequences:

- `Xlsform::setup()` fires queued jobs + network calls from the `created` event ([Xlsform.php:417](../../src/Models/OdkLink/Xlsform.php#L417)).
- `XlsformTemplate` `saved` iterates **every owner** (`$ownerType::all()`) and runs a `whereHas` existence check per owner on **every save**, ungated by `wasChanged()` ([XlsformTemplate.php:74](../../src/Models/OdkLink/XlsformTemplate.php#L74)). Toggling any unrelated column pays this cost.
- `Submission::updating` deletes all child entities and re-runs `handleUpdatedSubmissionContent()` synchronously, inline, no transaction ([Submission.php:64](../../src/Models/OdkLink/Submission.php#L64)).

**Recommendation:** move these to explicit service/action calls or dedicated jobs invoked from the UI/command layer. A `->save()` should persist, not deploy to a remote server.

### 1.2 Per-row event cascades during bulk import/clone

[SurveyRow.php:22](../../src/Models/OdkLink/SurveyRow.php#L22) and [ChoiceListEntry.php:51](../../src/Models/OdkLink/ChoiceListEntry.php#L51) each register a `saved` hook that does `->xlsforms()->update(['draft_needs_update' => true])`. During template import and `XlsformModuleVersion::cloneForOwner()` (thousands of rows), this fires a lazy relation resolve + an `UPDATE` **per row** to achieve one logical outcome. Set the flag once at the end of the import/clone job instead.

### 1.3 Attribute accessors are not pure — they do I/O and writes

Rendering a column can trigger network calls and row mutations:

- `enketoDraftUrl` → HTTP request + `$this->update([...])` + `refresh()` ([HasXlsformDrafts.php:139](../../src/Models/OdkLink/Abstracts/HasXlsformDrafts.php#L139)).
- `localeList` → `$this->locales()->sync(...)` on read ([Xlsform.php:188](../../src/Models/OdkLink/Xlsform.php#L188)).
- `liveSubmissionsCount` / `status` → ODK Central call / count query on read ([Xlsform.php:303](../../src/Models/OdkLink/Xlsform.php#L303), [Xlsform.php:129](../../src/Models/OdkLink/Xlsform.php#L129)).

Any Filament table or infolist listing these attributes silently fans out to per-row HTTP and DB writes. Convert to explicit methods; never put side effects in accessors.

### 1.4 The ODK service is a god-class assembled from stateful traits

[OdkLinkService.php:20](../../src/Services/OdkLinkService.php#L20) `use`s five "sub-service" traits that all depend on `$this->endpoint` and `$this->authenticate()` declared only on the host. They cannot be resolved from the container or unit-tested in isolation, and there is no compile-time guarantee the host provides those members. These are genuinely separate collaborators (projects/users/media/forms/submissions) that should share a small injected `OdkCentralClient` (endpoint + auth + `request()` helper), not be flattened into one class by trait.

### 1.5 Host-app coupling leaked into shipping code (the boundary is broken)

The package is config-driven for the owner/user model in _some_ places yet hardcodes host classes in others:

- `use App\Models\Project; use App\Models\ProjectActivity;` in [Dataset.php:5](../../src/Models/OdkLink/Dataset.php#L5) (unused imports leaked from the origin app).
- `use App\Models\Team;` in [ImportAllLanguageStrings.php](../../src/Jobs/ImportAllLanguageStrings.php) and [TestRemoveSub.php](../../src/Commands/TestRemoveSub.php).
- `use App\Models\User;` in [HandleXlsformTemplateAdded.php:5](../../src/Listeners/HandleXlsformTemplateAdded.php#L5) and both `Notify…` jobs.
- `HelperService::getModels()` scans the host's `App\Models` namespace via ClassFinder ([HelperService.php:26](../../src/Services/HelperService.php#L26)).
- A shipping model imports `Stats4sd\...\Tests\Models\Team` ([ChoiceListEntry.php:20](../../src/Models/OdkLink/ChoiceListEntry.php#L20)).
- `FinishXlsformTemplateImport` hardcodes `Role::findByName('Super Admin')` ([FinishXlsformTemplateImport.php:41](../../src/Jobs/FinishXlsformTemplateImport.php#L41)).
- `HOLPA CHANGE!` / app-specific TODO comments in [XlsformTemplate.php:235](../../src/Models/OdkLink/XlsformTemplate.php#L235).

**Recommendation:** define contracts (interfaces) for everything the host must supply and resolve them via config; a package must never `use App\...`. See the architecture doc.

### 1.6 Business logic embedded in Filament closures, duplicated across panels

The deploy/validate/publish workflow lives inside UI closures rather than services:

- ~85-line `afterValidation` closure doing XLSForm parsing + ODK round-trips + save + notifications in [CreateXlsformTemplate.php:71](../../src/Filament/OdkAdmin/Resources/XlsformTemplates/Pages/CreateXlsformTemplate.php#L71).
- The "Replace XLSForm" flow is duplicated near-verbatim in [ViewXlsformTemplate.php:58](../../src/Filament/OdkAdmin/Resources/XlsformTemplates/Pages/ViewXlsformTemplate.php#L58) and [XlsformTemplateTable.php:58](../../src/Filament/OdkAdmin/Resources/XlsformTemplates/Tables/XlsformTemplateTable.php#L58).

Extract to `OdkLinkService`/model methods so it is testable without Livewire and shared across panels.

### 1.7 Packaged UI has no extension seams

Resources hard-wire concrete schema/table classes with no config or override hook; widgets are shells whose logic sits in blade views that statically call `HelperService::getCurrentOwner()` and hardcode URL paths. A host wanting different columns/actions must subclass every layer. No translation layer exists at all (`$package->hasTranslations()` is commented out, [FilamentOdkLinkServiceProvider.php:46](../../src/FilamentOdkLinkServiceProvider.php#L46)), so every label is hardcoded English. This is the crux of the package-vs-app tension.

### 1.8 Pervasive duplication (DRY)

- `getSubmissions` vs `getOneSubmission` — ~100 near-duplicate lines that have already drifted and carry independent bugs ([OdkSubmissionService.php:113](../../src/Services/OdkLinkServices/OdkSubmissionService.php#L113) vs [:216](../../src/Services/OdkLinkServices/OdkSubmissionService.php#L216)).
- `PullSubmissionsFromXlsformQuietly` re-implements the whole fetch/parse loop (its own docblock flags this as tech debt).
- CSV-lookup generation duplicated in `OdkFormMediaService::prepareCsvFile` and `HelperService::createCsvLookupFile` — using **different disks** (`storage.media` vs `storage.xlsforms`).
- `expandMediaColumnHeaders()` / `getHeadingsFromProperties()` copy-pasted across the export classes.
- Two parallel deep relationship paths to `LanguageString` re-declared on three models with hand-written polymorphic key arrays PHPStan can't verify.

### 1.9 Polling re-downloads everything, O(n²) matching

`getSubmissions` fetches `…/Submissions?$expand=*` with no `$filter`/`$skip`/`$top`, then linear-scans the metadata per row ([OdkSubmissionService.php:143](../../src/Services/OdkLinkServices/OdkSubmissionService.php#L143)). For a form with thousands of submissions this dominates the poll cost. Page/filter server-side by `__system/submissionDate` and key the metadata into a lookup map once.

---

# Section 2 — Code gotchas (non-idiomatic / over-engineered / refactorable)

### Correctness-adjacent

- **`isDirty` inside a `saved` hook never fires.** [XlsformTemplate.php:70](../../src/Models/OdkLink/XlsformTemplate.php#L70) gates `afterXlsformFileUpdated()` on `isDirty('odk_draft_updated_at')`, but by the time `saved` runs the model is already synced so `isDirty()` is always false. Use `wasChanged()` (as `Xlsform` correctly does two files over).
- **`[A-z]` regex** in [XlsformTranslationHelper.php:59](../../src/Services/XlsformTranslationHelper.php#L59) also matches `` [ \ ] ^ _ ` ``. Use `[A-Za-z]`.
- **`importCsvFileToCollection` splits on server EOL, not file EOL** ([HelperService.php:71](../../src/Services/HelperService.php#L71)) — the comment claims "cross-platform" but `PHP_EOL` is the running server's ending; a CSV from ODK with `\r\n` leaves a trailing `\r` on every field. Normalise with `preg_split('/\r\n|\r|\n/', …)`.
- **`abort(500, …)` inside queue/service code** ([OdkFormMediaService.php:109,132,143](../../src/Services/OdkLinkServices/OdkFormMediaService.php#L109)) — `abort()` is an HTTP-request concept; use a typed domain exception.
- **`update()` then `save()`** (redundant second no-op save) at [OdkFormService.php:225](../../src/Services/OdkLinkServices/OdkFormService.php#L225).

### Dead code / leftovers

- **Empty trait `PublishesToOdkCentral`** ([PublishesToOdkCentral.php](../../src/Models/OdkLink/Traits/PublishesToOdkCentral.php)) — imported by `HasXlsformDrafts` but never `use`d in the body. `HasUploadedXlsformFile` and `HasOdkCentralAccount::bootHasOdkCentralAccount()` are likewise empty; `WithOdkCentralAccount` is an empty marker interface.
- **Large commented-out 409-retry block** in `createDraftForm` ([OdkFormService.php:56](../../src/Services/OdkLinkServices/OdkFormService.php#L56)).
- **Unreachable code after `throw`** — the notification block at [CreateXlsformTemplate.php:141](../../src/Filament/OdkAdmin/Resources/XlsformTemplates/Pages/CreateXlsformTemplate.php#L141) can never run (`throw $e; // TEMP` above it).
- **`$countModules` by-reference variable** in `syncWithTemplate()` is never declared or used ([Xlsform.php:261](../../src/Models/OdkLink/Xlsform.php#L261)).
- **Dead `$url` + unused `$response`** in `editOnEnketo` ([Submission.php:200](../../src/Models/OdkLink/Submission.php#L200)); it builds `$url` then returns `$enketoUrl`, and leaves `// TODO: handle 409/404`.
- **Stray filename literal**: `config('app.name') . ' Platform.php' . $this->id` ([Platform.php:26](../../src/Models/OdkLink/Platform.php#L26)) — the filename got pasted into the string.
- **`use function Laravel\Prompts\search;`** imported into a queued job ([PublishXlsformOnOdkCentral.php:19](../../src/Jobs/XlsformDeployment/PublishXlsformOnOdkCentral.php#L19)); plus unused `Carbon`/`UploadedFile`/`HasXlsformDrafts` imports across the deploy jobs.
- **`/** @noinspection ALL \*/`\*\* at the top of [HelperService.php:1](../../src/Services/HelperService.php#L1) suppresses all static analysis on the file.

### Guideline deviations (`.claude/laravel-php-guidelines.md`)

- Single-letter catch variables (`$e`) throughout the Filament pages and `UpdateXlsformFile`; guideline requires `$exception`.
- Inline FQCNs instead of `use` imports: `\Exception`, `\Throwable`, `\BackedEnum`, `\Illuminate\Http\Client\Response` (e.g. [OdkLinkService.php:51](../../src/Services/OdkLinkService.php#L51), resource files).
- Compound `&&` conditions not nested ([OdkFormService.php:135](../../src/Services/OdkLinkServices/OdkFormService.php#L135), `ProcessOdkSubmission.php:32`, others).
- `else` where an early return/ternary is cleaner (`Submission::addEntry`, `DatasetForm.php:33`, `XlsformTemplateForm.php:91`).
- `env()` outside config in [GenerateSubmissions.php:32](../../src/Commands/GenerateSubmissions.php#L32) (breaks under config caching; duplicates `config('filament-odk-link.odk.*')`).
- Wrong-case `$this->BelongsToMany(...)` ([Language.php:56](../../src/Models/OdkLink/XlsformLanguages/Language.php#L56), [Locale.php:118](../../src/Models/OdkLink/XlsformLanguages/Locale.php#L118)).
- Missing `void`/return types on many command/page methods; untyped `authenticateAsUser($data)`.
- Inconsistent cast style: `protected $casts = []` everywhere except `casts(): array` in [XlsformModule.php:25](../../src/Models/OdkLink/XlsformModule.php#L25).
- `$guarded = []` on every model, including submission-ingested ones fed from ODK Central.

### Over-engineering

- Two parallel maatwebsite interfaces (`ToCollection` + `ToModel` + `WithUpserts`) on [XlsformTemplateChoicesImport.php:20](../../src/Imports/XlsformTemplate/XlsformTemplateChoicesImport.php#L20) where only `collection()` is used — `uniqueBy()`/`ToModel` are dead.
- `Locale::status` builds an expensive `$allModuleVersions` traversal that is never used ([Locale.php:165](../../src/Models/OdkLink/XlsformLanguages/Locale.php#L165)).

---

# Section 3 — Actual bugs

Grouped by confidence. Items marked ✅ were verified against the source during this review; ⚠️ are high-confidence from the code but not independently reproduced.

## Critical / High

### B1 ✅ `TestRemoveSub` "Specific" wipes the whole database (and it ships)

[TestRemoveSub.php:38](../../src/Commands/TestRemoveSub.php#L38). The `Specific` branch deletes the chosen form's submissions, then execution **falls through** to an unconditional block that deletes every `Submission`/`Entity` and `Cache::flush()`es. The `Specific` case also calls `->forceDelete()` on an Eloquent Collection (no such method → `BadMethodCallException`). Worse, `getCommands()` auto-registers every file in `src/Commands`, so this destructive `odk:trs` command is installed in every host. _Failure:_ an operator cleaning one team's test data wipes all tenants' submissions and flushes the host cache.
**Fix:** exclude `Test*`/debug commands from registration (or delete them); if kept for local dev, gate behind `App::environment('local')` and remove the fall-through.

**Status — ✅ fixed (branch `pre-publish-fixes`):** the command now early-returns unless `config('app.env') === 'local'`, the delete-all block moved into an `else` (no more fall-through from "Specific"), and the unused `use App\Models\Team` import was removed. The "Specific" branch now calls `$submission->forceDelete()` on each model inside the `each()` closure ([TestRemoveSub.php:51-54](../../src/Commands/TestRemoveSub.php#L51)), so both branches delete correctly.

### B2 ✅ Team users can mutate/delete Platform-owned templates

[TeamXlsformTemplateResource.php](../../src/Filament/OdkTeam/Resources/TeamXlsformTemplates/TeamXlsformTemplateResource.php) extends the admin resource and **does not override `table()`** or the pages' header actions, while its query deliberately exposes `available` Platform templates to every tenant. So a team user sees the inline `CheckboxColumn::make('available')`, Edit, Replace-XLSForm and **Delete** actions on shared records ([XlsformTemplateTable.php:34](../../src/Filament/OdkAdmin/Resources/XlsformTemplates/Tables/XlsformTemplateTable.php#L34)). No policies/`canAccess` exist anywhere in `src/Filament`. The intended safe table — `TeamXlsformTemplateTable` (deploy/download only) — is **dead code** (never referenced). _Failure:_ a tenant deletes or un-publishes a global template for all other tenants.
**Fix:** have the team resource use `TeamXlsformTemplateTable`, override header actions on the team pages, and add per-record authorization (a policy that checks ownership) rather than relying on query scoping alone.

**Status — ✅ fixed (branch `pre-publish-fixes`):** taken a different route than the suggested lock-down — the entire `src/Filament/OdkTeam/` tree (the `TeamXlsformTemplateResource`, its pages/tables, and `CustomOdkTemplatesWidget`) was deleted, so the destructive actions are no longer reachable from the team panel at all. **Note:** the `OdkLinkTeam` plugin still calls `discoverResources`/`discoverWidgets` on the now-empty directories (harmless no-op), and the team panel now provides **no** package resource — teams have no package-supplied way to browse/deploy templates, so the host app must supply its own team UI if that capability is wanted.

### B3 ✅ Numeric-zero answers dropped in repeat groups (data loss)

[OdkSubmissionService.php:464](../../src/Services/OdkLinkServices/OdkSubmissionService.php#L464): `… && $value != null && $value != '' && …`. In PHP `0 == null` is true, so integer/float `0` fails the guard and is never written as an `EntityValue`. The root-section equivalent at [:366](../../src/Services/OdkLinkServices/OdkSubmissionService.php#L366) uses strict `!== null`, so root and repeat sections disagree on identical data. _Failure:_ `number_of_cows = 0` in a repeat group is silently lost.
**Fix:** use `$value !== null` (and `!== ''`).

**Status — ✅ fixed (branch `pre-publish-fixes`):** guard is now `$value !== null` at [OdkSubmissionService.php:464](../../src/Services/OdkLinkServices/OdkSubmissionService.php#L464), matching the strict root-section check. Numeric `0` is preserved.

### B4 ✅ `ChoiceListEntry` owner global scope leaks via unwrapped `OR`

[ChoiceListEntry.php:41](../../src/Models/OdkLink/ChoiceListEntry.php#L41): `$query->where('owner_id', X)->orWhereNull('owner_id')` is applied at top level, so any later constraint composes as `(… AND owner_id = X) OR owner_id IS NULL`. _Failure:_ `ChoiceListEntry::where('choice_list_id', 5)->get()` in a tenant context returns **all global entries from every list**. Contrast the correctly-wrapped scope in [Xlsform.php:91](../../src/Models/OdkLink/Xlsform.php#L91).
**Fix:** wrap in a closure: `->where(fn ($q) => $q->where(...)->orWhereNull(...))`.

**Status — ✅ fixed (branch `pre-publish-fixes`):** the owner/`orWhereNull` pair is now wrapped in a nested closure at [ChoiceListEntry.php:41](../../src/Models/OdkLink/ChoiceListEntry.php#L41), so it composes as `(owner_id = X OR owner_id IS NULL)` and no longer leaks past later constraints.

### B5 ✅ ChoiceList relation manager uses Filament v3 APIs (fatal)

[ChoiceListEntriesRelationManager.php](../../src/Filament/OdkAdmin/Resources/ChoiceListResource/RelationManagers/ChoiceListEntriesRelationManager.php): `use Filament\Forms\Get;` (v3 path) and `Tables\Actions\{Create,Edit,Delete,BulkActionGroup,DeleteBulkAction}` via `->actions()`/`->bulkActions()`/`->headerActions()` — all removed in v4/v5 (now `Filament\Actions\*` + `->recordActions()`/`->toolbarActions()`). Every other table uses the v5 form. _Failure:_ class-not-found / unknown-method when the relation manager renders.

**Status — ✅ fixed (branch `pre-publish-fixes`):** migrated to v5 — `Get` now imported from `Filament\Schemas\Components\Utilities\Get`, actions from `Filament\Actions\*`, and `->recordActions()`/`->groupedBulkActions()` used (matching `VariablesRelationManagerTable`). Also fixed the related [:47](../../src/Filament/OdkAdmin/Resources/ChoiceListResource/RelationManagers/ChoiceListEntriesRelationManager.php#L47) dead `helperText($property['helper_text'])` → `$property['hint'] ?? null` (the repeater keys are `name`/`label`/`hint`) and dropped unused imports. PHPStan clean on the file.

### B6 ✅ `UpdateXlsformDrafts` "TEMP" mutation of form id 1

[UpdateXlsformDrafts.php:29](../../src/Commands/UpdateXlsformDrafts.php#L29): `Xlsform::find(1)->update(['draft_needs_update' => true]); // TEMP` runs on every invocation — forcing an unwanted redeploy of whatever has id 1, and throwing `Call to a member function update() on null` if no such form exists (aborting before the real loop).

**Status — ✅ fixed (branch `pre-publish-fixes`):** the TEMP `Xlsform::find(1)->update(...)` line was removed; the command now goes straight to the `draft_needs_update` loop.

### B7 ✅ `EntityExport` queries a column that doesn't exist

[EntityExport.php:85](../../src/Exports/EntityExport.php#L85) and [:43](../../src/Exports/EntityExport.php#L43) filter `$entity->values->whereIn('dataset_variable_id', …)`, but `EntityValue` is keyed by `dataset_variable_name` (`dataset_variable_id` appears nowhere else in `src/`). Line 43 then does `->first()->value` on the empty result → `TypeError` whenever a dataset has a parent; line 85 returns blank rows otherwise. Even fixed, `getEntityValues()` maps in entity order rather than projecting onto `$headings`, so a missing value shifts every later column.
**Fix:** use `dataset_variable_name`; project values positionally onto headings.

**Status — ✅ fixed (branch `pre-publish-fixes`):** both sites now use `dataset_variable_name`, the parent lookup at [:43](../../src/Exports/EntityExport.php#L43) is null-safe (`?->value`), and `getEntityValues()` projects positionally onto `$headings` via `keyBy('dataset_variable_name')` so a missing value yields `null` in place instead of shifting later columns. Covered by new [EntityExportTest.php](../../tests/Unit/Exports/EntityExportTest.php) (correct keying, positional projection, numeric-zero preserved).

### B8 ✅ Broken lookup-list completion relation → SQL error

[HasXlsforms.php:81](../../src/Models/OdkLink/Traits/HasXlsforms.php#L81) wires `choiceLists()` to `ChoiceListEntry::class` (table `choice_list_entries`), but `hasCompletedLookupList()` at [:116](../../src/Models/OdkLink/Traits/HasXlsforms.php#L116) queries `choice_lists.id` — a table not in the join. _Failure:_ every `markLookupListAsComplete()` / `…AsInComplete()` / `hasCompletedLookupList()` call errors with "Unknown column 'choice_lists.id'". Related model should be `ChoiceList::class`.

**Status — ✅ fixed (branch `pre-publish-fixes`):** `choiceLists()` now targets `ChoiceList::class`, matching the `choice_list_owner.choice_list_id → choice_lists.id` FK (migration `008`) and the `where('choice_lists.id', …)` in `hasCompletedLookupList()`. The 5 tests in [HasXlsformsTest.php](../../tests/Unit/Models/HasXlsformsTest.php) that previously asserted the `QueryException` were rewritten to assert correct behaviour (complete → `true`, incomplete → `null`), plus a new "no pivot row → null" case.

## Medium

### B9 ✅ `getOneSubmission` "updated" filter is a constant, never matches

[OdkSubmissionService.php:247](../../src/Services/OdkLinkServices/OdkSubmissionService.php#L247): `$currentSubmissionLatestIds->doesntContain(['currentVersion']['instanceId'])` evaluates `['currentVersion']['instanceId']` as a literal array indexed by a missing string key → `null`, independent of the row. Correct code (present in `getSubmissions`) is `$result['currentVersion']['instanceId']`. _Failure:_ an edited submission pulled via `getOneSubmission` is never recognised as updated, so the change isn't re-ingested.

**Status — ✅ fixed (branch `pre-publish-fixes`):** the filter now reads `$currentSubmissionLatestIds->doesntContain($result['currentVersion']['instanceId'])`, matching the correct `getSubmissions` implementation.

### B10 ⚠️ `updateUser()` / `deleteUser()` declare `: array` but return nothing → `TypeError`

[OdkUserService.php:157](../../src/Services/OdkLinkServices/OdkUserService.php#L157) and [:170](../../src/Services/OdkLinkServices/OdkUserService.php#L170) are stubs (`$token = $this->authenticate();`, no return) behind an `: array` signature. Calling either fatals.

**Status - ✅ fixed (branch `pre-publish-fixed`):** removed both methods; they were stubs; unused and un-expanded code.

### B11 ✅ `AddMissingChoiceListStrings` null-deref + non-idempotent duplicates

[AddMissingChoiceListStrings.php:56](../../src/Jobs/AddMissingChoiceListStrings.php#L56): `$matchingEntry` from a `->first()` filtered on name + properties + cascade_filter can be `null`, then `$matchingEntry->languageStrings` throws. It also `->insert()`s language strings with no existence guard, so a retry after a mid-job failure double-inserts every already-processed entry (duplicated localised labels in deployed forms).

**Status — ✅ fixed (branch `pre-publish-fixes`):** added a `! $matchingEntry` guard so an entry with no translated counterpart is skipped instead of dereferencing null. The outer query now selects choice lists with **any** entry still missing strings (`whereHas('choiceListEntries', fn ($q) => $q->whereDoesntHave('languageStrings'))`) instead of requiring **none** have strings, and the per-entry loop skips already-populated entries and writes via `updateOrCreate` (keyed on `locale_id`/`language_string_type_id`) instead of a raw `insert()` — so a retry after a mid-job failure backfills only what's still missing, without throwing on the `unique_language_string` constraint. Covered by [AddMissingChoiceListStringsTest.php](../../tests/Unit/Jobs/AddMissingChoiceListStringsTest.php).

### B12 ⚠️ `UpdateXlsformTitleInFile` corrupts cell coordinates at row ≥ 10 / column ≥ AA

[UpdateXlsformTitleInFile.php:47](../../src/Services/UpdateXlsformTitleInFile.php#L47): `str_split('A10')` → `['A','1','0']`, so the target becomes `A2` not `A11`. The form id/title is written to the wrong cell for any settings sheet whose header is at/after row 10, and the file deploys with an unchanged/incorrect id. Use `Coordinate::coordinateFromString()`.

### B13 ⚠️ `PrepareSurveyRowPaths` pops an empty stack on unbalanced `end repeat`

[PrepareSurveyRowPaths.php:70](../../src/Jobs/PrepareSurveyRowPaths.php#L70): `pop()` on an empty stack returns `null`, `substr(null,…)` (deprecated in 8.4) yields `''`, `strrpos('', '/')` is `false`, and path bookkeeping silently corrupts for all later rows rather than failing loudly.

### B14 ⚠️ `publishForm` proceeds when the draft-existence check itself failed

[OdkFormService.php:192](../../src/Services/OdkLinkServices/OdkFormService.php#L192): `$draftCheck` is issued without `->throw()` and decided via `if (! $draftCheck->notFound())`. Any non-404 failure (500, 401 from an expired token) is not a 404, so it publishes anyway against possibly-stale state. Check `->successful()` explicitly.

**Status — ✅ fixed (branch `pre-publish-fixes`):** `$draftCheck->throw()` now runs inside the `! notFound()` branch, so a 404 still falls through to the deployed version (intended), a 200 publishes, and any other error (5xx/401) throws instead of publishing against stale state. The subsequent version/flag updates were also wrapped in a `DB::transaction()` (the HTTP publish call stays outside it), and the method return type changed to `void`. Note: leftover stale `@return XlsformVersion` docblock at [:184](../../src/Services/OdkLinkServices/OdkFormService.php#L184) should be dropped.

### B15 ⚠️ `ProcessOdkSubmission` dispatched before its media is attached

[OdkSubmissionService.php:196](../../src/Services/OdkLinkServices/OdkSubmissionService.php#L196): the processing job is queued (async), then attachments are downloaded synchronously afterward. On a real queue the host-app `submission.process_method` can run against a submission whose attachments aren't present yet.

### B16 ⚠️ Broken Entity/DatasetVariable relations

- `Entity::datasetVariables()` sets `relatedPivotKey: 'dataset_variable_name'` but leaves the related key as `id`, so the join compares `dataset_variables.id = entity_values.dataset_variable_name` ([Entity.php:79](../../src/Models/OdkLink/Entity.php#L79)) — needs `relatedKey: 'name'`.
- `DatasetVariable::values()` uses `hasMany(EntityValue::class, 'entity_id')` ([DatasetVariable.php:41](../../src/Models/OdkLink/DatasetVariable.php#L41)) — `entity_values.entity_id` references `entities.id`, not the variable — returns garbage.

### B17 ⚠️ `Locale::owners()` selects a misspelled pivot column → SQL error

[Locale.php:118](../../src/Models/OdkLink/XlsformLanguages/Locale.php#L118): `->withPivot(['langauge_id'])` (typo) adds `language_owner.langauge_id` to the SELECT → "Unknown column" on every access of `$locale->owners`.

Status: Fixed

### B18 ⚠️ `Entity::addValues()` de-dupe compounds the suffix

[Entity.php:113](../../src/Models/OdkLink/Entity.php#L113): the suffix is appended to the already-mutated name, producing `field`, `field.1`, `field.1.2`, `field.1.2.3` … instead of `field.1`, `field.2`, `field.3` for repeated identical variable names.

## Lower / latent (null-safety & robustness)

- **Unguarded `owner->odkProject->id`** in QR/link generation ([HasXlsformDrafts.php:106](../../src/Models/OdkLink/Abstracts/HasXlsformDrafts.php#L106), [Xlsform.php:248](../../src/Models/OdkLink/Xlsform.php#L248), [XlsformTemplate.php:389](../../src/Models/OdkLink/XlsformTemplate.php#L389), [Submission.php:186](../../src/Models/OdkLink/Submission.php#L186)) — fatals when an owner has no ODK project (the exact scenario a recent hotfix acknowledged).
- **`makeMultiSelectBooleans` null choice-list deref** ([OdkSubmissionService.php:544](../../src/Services/OdkLinkServices/OdkSubmissionService.php#L544)) — no guard when the parsed `select_multiple` list name isn't in the loaded `$choices`.
- **N+1 choice-list query inside a repeat-group loop** ([OdkSubmissionService.php:437](../../src/Services/OdkLinkServices/OdkSubmissionService.php#L437)); N+1 version lookup per submission ([:163](../../src/Services/OdkLinkServices/OdkSubmissionService.php#L163)).
- **Export N+1**: `getLanguageStrings()` calls the relation as a query builder, bypassing eager loading — ~`6·L·R` queries per survey export ([ExportsXlsformContent.php:36](../../src/Exports/XlsformExport/ExportsXlsformContent.php#L36)).
- **Import null-derefs on malformed workbooks**: unmatched module in [GetsModuleNamesPerRow.php:24](../../src/Imports/XlsformTemplate/GetsModuleNamesPerRow.php#L24); unknown choice list in [XlsformTemplateSurveyImport.php:61](../../src/Imports/XlsformTemplate/XlsformTemplateSurveyImport.php#L61); `ChoiceListModelsExport::headings()` on an empty list ([ChoiceListModelsExport.php:72](../../src/Exports/ChoiceListModelsExport.php#L72)).
- **`SurveyExport` null-safety lie** — `?Xlsform $xlsform = null` constructor immediately dereferences it ([SurveyExport.php:17](../../src/Exports/SurveyExport.php#L17)).
- **`DatasetAsMediaAttachmentExport` emits `name`/`label` twice** (intended exclusions commented out, [DatasetAsMediaAttachmentExport.php:66](../../src/Exports/DatasetAsMediaAttachmentExport.php#L66)) — corrupts the CSV ODK reads for `select_*_from_file`.
- **Token never invalidated**: `authenticate()` caches for 20h with no `Cache::forget` / retry-on-401 anywhere ([OdkLinkService.php:38](../../src/Services/OdkLinkService.php#L38)); an early server-side session expiry wedges the whole integration until TTL elapses.
- **Hardcoded panel route** `route('filament.admin.resources.datasets.view', …)` ([DatasetTable.php:30](../../src/Filament/OdkAdmin/Resources/Datasets/Tables/DatasetTable.php#L30)) → `RouteNotFoundException` on any panel not named `admin`; use `DatasetResource::getUrl(...)`.
- **`helperText($property['helper_text'])`** references a key the repeater never defines (keys are `name`/`label`/`hint`) ([ChoiceListEntriesRelationManager.php:47](../../src/Filament/OdkAdmin/Resources/ChoiceListResource/RelationManagers/ChoiceListEntriesRelationManager.php#L47)).
- **Broken anchor markup** (missing closing quote on `href`) in `custom-odk-templates-widget.blade.php:5`.
- **`# of Active Xlsforms` counts all xlsforms** (`->counts('xlsforms')`, not active) in the relation-manager tables.
- **`createProjectAppUser` display name** relies on `+` binding tighter than `.` — works on 8.4 but fragile ([OdkProjectService.php:52](../../src/Services/OdkLinkServices/OdkProjectService.php#L52)).
- **`XlsformValidationHelper` only accepts `_en`** while the accepted/valid header format is `label::English (en)` — flags valid forms as errors ([XlsformValidationHelper.php:119](../../src/Services/XlsformValidationHelper.php#L119)).

---

## Suggested remediation order

1. **Ship-blockers:** B1, B2, B6 (remove/gate debug + destructive artefacts; lock down the team panel). These are safety issues, not just bugs. — _B1, B2, B6 ✅ done on branch `pre-publish-fixes`._
2. **Data integrity:** B3, B4, B7, B8, B9, B16, B17 — wrong data or hard errors on used paths. — _B3, B4, B7, B8, B9 ✅ done; B5 (from the table above) also ✅ done. B16, B17 still open._
3. **Robustness:** the null-deref / N+1 / token-invalidation cluster.
4. **Architecture (larger, staged):** move orchestration out of model events/accessors (1.1–1.3), split the ODK service (1.4), and remove host-app coupling (1.5). The last one feeds directly into the packaging decision.

A companion issue could track the DRY consolidation (1.8) as a single refactor pass once the boundary work lands.
