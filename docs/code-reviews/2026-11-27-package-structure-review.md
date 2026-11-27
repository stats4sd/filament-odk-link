# Structural review of `odk-link-core` and `odk-link-filament`

**Date**: 2026-11-27
**Branch**: `package-review-and-redesign` (= `dev` at `116c1fc`)
**Reviewer**: Claude (automated multi-angle review: three parallel reviewers, one per area, synthesised)
**Scope**: all of `packages/odk-link-core` (132 PHP files, about 11,300 lines in `src`), all of `packages/odk-link-filament` (51 PHP files, 15 views), `apps/reference` as their host, and the Groundswell app (`stats4sd/groundswell_platform`, read through `gh`) as evidence of how a real host uses them.

Part B2 of [docs/plans/2026-11-27-package-review-and-redesign.md](../plans/2026-11-27-package-review-and-redesign.md). The proposed target design that follows from these findings is in [docs/plans/2026-11-27-target-design.md](../plans/2026-11-27-target-design.md). The requirements the redesign must keep are in [docs/plans/2026-11-27-hotfix-requirements-ledger.md](../plans/2026-11-27-hotfix-requirements-ledger.md).

## How to read this

- **Method**: static reading of every in-scope file, migrations and tests. The Filament suite was run (17 passed). Nothing was driven in a browser and nothing ran against a real ODK Central. Claims marked *(inferred)* follow from the code plus database or framework semantics and were not reproduced.
- **IDs**: `F` = form-definition half of the core, `P` = post-publish half and ODK transport, `U` = Filament UI and reference app. Earlier reviews are cited as "package-review" ([2026-07-06-package-review.md](2026-07-06-package-review.md)) and "codex" ([codex-review/2026-09-08-package-review.md](codex-review/2026-09-08-package-review.md)); both number bugs `B1…`, for different things.
- **Paths**: in part 1, `M/` = `packages/odk-link-core/src/Models/OdkLink/`, `I/` = `…/src/Imports/XlsformTemplate/`, `J/` = `…/src/Jobs/`, `E/` = `…/src/Exports/`, `mig/` = `…/database/migrations/`. In part 3, `R/` = `packages/odk-link-filament/src/OdkAdmin/Resources/`, `F/` = `packages/odk-link-filament/`, `C/` = `packages/odk-link-core/`, `GS:` = the Groundswell repo.
- **Severity** is judged for a non-expert user going slightly off the main path.

## Summary

1. **The form-definition half needs a redesign, not repair.** Three critical defects are still on `dev` and are structural: a template re-import re-creates every global choice entry with a new id (F1), deletes team content in any module variant that keeps its module id (F2), and deletes empty choice lists belonging to other templates (F3). They come from the schema (per-version choice lists, NULL-able unique keys, flag-then-sweep cleanup over template-wide relations), so no amount of guarding removes them. The hotfix branch patched the symptoms; the causes are confirmed here independently.
2. **On a fresh database the current core does not complete a draft deploy.** Code reads and writes columns and tables no core migration creates (F9). Groundswell works only because the app carries its own copies of the package schema.
3. **State is tracked by flags that most writes never set.** `draft_needs_update`, `live_needs_update`, `has_latest_template`, `processing` and five `updated_during_import` flags are maintained by model events, but imports, bulk updates and pivot writes bypass events (F7, F8, F10). Publish trusts those flags.
4. **The admin UI has no authorisation, no guards on destructive actions and no tests of any action** (U4–U7, U25). Deleting a template from a dataset's relation tab deletes the template itself and its ODK form (U5).
5. **The one real host customised none of the admin UI.** Groundswell uses the plugin unmodified and built about 5,200 lines of team-facing UI itself, of which roughly 2,400 are generic. This contradicts the premise of step 7 as planned (publish the admin UI as host-owned stubs, delete the plugin). See part 3, "The stubs question".
6. **The post-publish half is untested in use and has no consumer in this repo, but mostly needs targeted fixes.** The exception is ingestion (pull, store, media, project), which is not idempotent and should be rebuilt as staged jobs. Nine of its predicted failures were reproduced by an executable scenario (part 4): an edited submission is projected twice, a retried job adds a second entity tree, one unknown form version blocks all later submissions on every poll, location answers are dropped, deletions on Central are never mirrored, and a guest can trigger the submission update route.
7. **Security items that should not wait for the redesign**: the enumerator QR code carries a token with the project manager role (P1, to be confirmed against a real Central), the unauthenticated submission update route (P2), and lookup CSVs that include other owners' records (P3).

## Part 1. Core, form-definition half

Templates, modules, module versions, survey rows, choice lists, language strings, locales, build, deploy and publish.

### The six root causes, checked against `dev`

| # | Cause | Verdict | Evidence |
| --- | --- | --- | --- |
| 1 | No canonical choice list | Confirmed | `mig/005:28` unique `(xlsform_module_version_id, list_name)`. "Same entry" is re-derived in six places: fan-out by `list_name` (`I/XlsformTemplateChoicesImport.php:30-35`); name + list_name + all properties (`I/XlsformTemplateLanguageStringImport.php:93-122`); label copy between same-named lists (`J/AddMissingChoiceListStrings.php:39-64`); `GROUP BY … max(id)` (`E/XlsformExport/XlsformChoicesExport.php:43-58`); `${list_name}_info.csv` media match (`J/FinishChoiceListEntryImport.php:81-83`); `allow_choice_duplicates = yes` hiding the result (`E/XlsformExport/XlsformSettingsExport.php:34`). |
| 2 | Template-wide relations include team versions | Confirmed, wider than thought | The `hasManyDeep` relations at `M/XlsformTemplate.php:320-369` have no `is_default` or owner filter and are used for the sweep (`I/XlsformTemplateWorkbookImport.php:46-63`), flag resets (`J/FinishSurveyRowImport.php:23`, `J/FinishChoiceListEntryImport.php:93`, `J/FinishLanguageStringImport.php:34,40`), string deletes (`I/XlsformTemplateLanguageStringImport.php:168-187`), path computation (`J/PrepareSurveyRowPaths.php:30`), choice fan-out and the translation export (`E/XlsformTemplateTranslationsExport.php:84,88`). A team variant is reachable exactly when `xlsform_module_id` is set, which `cloneForOwner` preserves. "Local …" versions escape only because their module id is NULL. |
| 3 | Identity by name or nullable key | Confirmed, worse than thought | See F1. Also `mig/004:30` unique `(xlsform_module_id, owner_id, name)` is defeated by either NULL; `mig/007:31` omits `owner_id`. |
| 4 | Side effects in model events that writes bypass | Confirmed | Hooks: `M/SurveyRow.php:22-26`, `M/ChoiceListEntry.php:48-53`, `M/RequiredMedia.php:34-38`, `M/Traits/HasLanguageStrings.php:17-42`. Bypassing writes: Excel `WithUpserts` imports, `ChoiceListEntry::upsert`, every relation `->update()`, pivot `sync/attach/detach` (`M/Xlsform.php:264,273,316-317`, `M/ChoiceListEntry.php:110-117`), and all `LanguageString` writes (no hook at all). |
| 5 | `processing` as a non-atomic lock | Confirmed, and released early | See F8. |
| 6 | No locale-inheritance rule, no missing-owner rule, ambient owner scope | Confirmed | See F12, F13. |

### Findings

| ID | Sev | Finding and evidence | What a user sees | Prior id |
| --- | --- | --- | --- | --- |
| F1 | Critical | Global choice entries are re-created with new ids on every template import. `ChoiceListEntry::upsert(..., ['name','choice_list_id','cascade_filter'])` (`I/XlsformTemplateChoicesImport.php:66`) relies on `mig/007:31`; MySQL and SQLite treat NULL as distinct in a unique index, so a NULL-filter row never conflicts and is inserted again. The old row is then destroyed by the sweep (`I/XlsformTemplateWorkbookImport.php:56-63`), which deletes its language strings (`M/ChoiceListEntry.php:55-59`) and cascades its removal pivots (`mig/024:19`). Independently confirmed by the hotfix author's comment in `f9d12cb`. | Admin re-uploads a template to fix a typo: every team's "removed from context" choices come back, every non-default-locale choice translation is deleted, and translation files exported earlier carry dead ids. | New |
| F2 | Critical | Template re-import deletes team content under any owner variant that keeps `xlsform_module_id`. Rows not flagged in this import are destroyed across all versions (`I/XlsformTemplateWorkbookImport.php:46-53`); default-locale survey strings likewise (`I/XlsformTemplateLanguageStringImport.php:180-187`); choices fan out into the clone's lists and overwrite properties (`I/XlsformTemplateChoicesImport.php:30-66`). | A team clones a module to edit question text; the next admin upload empties the clone and the team's form loses that module's questions on the next build. | codex B3 |
| F3 | Critical | `ChoiceList::has('choiceListEntries','=',0)->delete()` is global (`I/XlsformTemplateWorkbookImport.php:65`). Query-builder delete, so no model events; `required_media.choice_list_id` (restrict, `mig/011:19`) can make it throw on MySQL. | A host-built local list a team has not filled yet is deleted by any template import by anyone; survey rows pointing at it get `choice_list_id = NULL`. Or the import fails with a foreign-key error after rows were already written. | codex B4; FK part new |
| F4 | High | One `ChoiceList` per module version. An entry in a list used by N modules exists N times; the importer attaches labels to the first match only (`I/XlsformTemplateLanguageStringImport.php:122`) and `AddMissingChoiceListStrings` copies to the rest, skipping any entry that already has any string (`J/AddMissingChoiceListStrings.php:56`). The per-heading sweep deletes the copies' default strings each import (`:168-178`). Export keeps `max(id)` per group. | A copy holding a translation in another locale never gets its default label back after a re-import, so the form shows a blank choice *(inferred)*. Completion pivots and required-media links point at one arbitrary copy. | package-review B11 residual |
| F5 | High | Variant identity is a display name. `firstOrCreate(['owner_id', 'name' => 'Local '.$module->name])` (`M/Xlsform.php:268-271`) and the lookup at `:304-307` ignore module and template. `cloneForOwner` names `"<module> - <owner>"` (`M/XlsformModuleVersion.php:146`), which the replacement lookup never finds; a second clone for the same owner hits the unique index. Module identity is also a name, and generic modules embed the template title (`I/XlsformModuleImport.php:45,55,83`). Modules missing from a new workbook are never removed. | Two templates with a module called "location": editing one team's local module changes both forms. Renaming a template creates a second set of "Unspecified Module" modules; forms keep the emptied old ones. Clicking "customise" twice gives an SQL error. | codex B10, C3; module-name part new |
| F6 | High | `syncWithTemplate()` re-adds every module whose id is not among the form's selected versions (`M/Xlsform.php:258-264`) and runs from the `saved` hook whenever `has_latest_template` is false (`:58-60`), which `markAllAsNotCurrent()` sets for all forms on each template draft update (`M/XlsformTemplate.php:515-518`). | A team deselects an optional module; the next unrelated save of the form, including the save at the start of the deploy job, puts it back. | codex A1 |
| F7 | High | `draft_needs_update` is unreliable and `publishForm()` trusts it (`M/Xlsform.php:447`). Imports upsert, label edits write `LanguageString` (no hook), module selection and choice removal are pivot writes: none set the flag. | Admin fixes a constraint in the template, team presses Publish: the old draft is published and the UI says it is live and current. | New |
| F8 | High | Locking. `deployDraft()` tests an in-memory `processing` (`M/Xlsform.php:420`), `generateXlsfile()` sets it with a plain update (`:401`), and `UpdateXlsformFile` clears it in `finally` before the deploy job in the same chain runs (`J/XlsformDeployment/UpdateXlsformFile.php:36-37`). The publish-then-redeploy path never sets it. The import listener sets it without checking it (`src/Listeners/HandleXlsformTemplateAdded.php:43`). `publishForm()` returns null silently when busy (`M/Xlsform.php:443-445`). | Double-click on Deploy, or deploy plus the scheduled `UpdateXlsformDrafts`, runs two uploads against one ODK form. Two template uploads in quick succession share the sweep flags and delete each other's rows. Publish while busy does nothing and says nothing. | codex A2; early release new |
| F9 | High | Schema used by code but absent from migrations: `xlsform_versions.is_draft` (`J/…/DeployDraftXlsformToOdkCentral.php:86`, `OdkFormService.php:303`, `M/Xlsform.php:148,174`); `xlsforms.has_locales` and table `locale_xlsform` (`M/Xlsform.php:47,184-187,195`); `xlsform_module_versions.country_id` (`M/XlsformModuleVersion.php:52`); `choice_list_entries.model_type/model_id` (`M/ChoiceListEntry.php:83`); `xlsform_modules.updated_during_import` (`I/XlsformTemplateModuleTypeImport.php:19`); `xlsform_template_sections.data_subject_dataset_id` (`M/XlsformTemplateSection.php:54`). | On a fresh database the first draft deploy succeeds on ODK Central then fails locally; the status column errors for any deployed, inactive form. | codex B7; rest new |
| F10 | High | Deploy toggles `live_needs_update = ! live_needs_update` (`J/…/DeployDraftXlsformToOdkCentral.php:77`); publish clears it then redeploys the draft, which toggles it back (`J/…/PublishXlsformOnOdkCentral.php:40-43`). Events are derived from those transitions in `saved` (`M/Xlsform.php:63-75`). | Every published form immediately shows "live needs update". A second draft deploy fires `XlsformWasPublished` without a publish. | codex B8 |
| F11 | High | The form-file round trip is lossy and partly silent. Zero values dropped by bare `filter()` (`I/XlsformTemplateSurveyImport.php:40,46`); `required` expressions coerced to false (`:82-86`, `M/SurveyRow.php:37`, `mig/006:26`); `trigger` imported but never exported; settings replaced and `instance_name` fixed. New: a translated column whose language is not in `languages` fails the header regex (`src/Services/XlsformTranslationHelper.php:59`), lands in `properties`, and is stripped on export because it contains `::` (`E/XlsformExport/ExportsXlsformContent.php:80`). The pipeline depends on the host's Laravel Excel heading formatter being `slug`, which mangles `bind::…` and `body::…` headers irreversibly. Choice names have spaces replaced on export only. | Admin uploads a form with a Swahili column and no Swahili language row: import reports success, deployed forms have no Swahili. A conditional `required` becomes "not required". | codex A3, B11–B13; language, slug and name parts new |
| F12 | High | No label resolution rule. Export reads the exact (row, locale, type) string or emits `''` (`E/XlsformExport/ExportsXlsformContent.php:42-45`). `defaultLabel` hard-codes `en`. Staleness is one boolean per (version, locale), set for every non-imported locale on every import whether or not anything changed (`J/LinkModuleVersionToLocales.php:66-82`); its sweep flag is never reset. `Locale::created` attaches the new locale to every default version of every template and loops all owners. | A team locale with 95% of strings translated deploys with 5% blank labels; ODK rejects the form or enumerators see empty questions. Any re-import marks every team translation "Needs updating". | New |
| F13 | High | Owner context. Ambient global scopes on `Xlsform` and `ChoiceListEntry` (`M/Xlsform.php:88-98`, `M/ChoiceListEntry.php:36-45`). `cloneForOwner($owner)` reads entries through the ambient scope, not its argument (`M/XlsformModuleVersion.php:157`). The translations export takes `$owner` but only uses it for headings (`E/XlsformTemplateTranslationsExport.php:47-54`). `$this->owner->…` is dereferenced unguarded (`M/Xlsform.php:205,250,269,305` and others). Foreign keys disagree on owner deletion: cascade (`mig/002:20`), null (`mig/004:21`), restrict (`mig/007:21`). | Team A's translation file contains team B's custom choices. After a team is soft-deleted, `UpdateXlsformDrafts` and template distribution crash on its forms. Hard-deleting a team with custom choices fails. | codex A5 (partly fixed); missing-owner part new |
| F14 | Medium | Orchestration in `saved`/`created`/`deleting` hooks. Creating an `Xlsform` syncs and deploys; every save of an available template loads all owners and may create and deploy a form per owner (`M/XlsformTemplate.php:78-97`); each `RequiredMedia` save re-enters that loop; deletes call ODK Central first. Import starts from a media event with no collection check (`src/Listeners/HandleXlsformTemplateAdded.php:31-33`). | Ticking "available" makes the request create and deploy forms for every team. ODK Central being down blocks local deletion. Attaching an image to a template starts a workbook import. | package-review 1.1, codex A2, B14 |
| F15 | Medium | Import chain ordering and cost. The choice-list import is queued separately from the workbook chain while the survey import dereferences the list (`I/XlsformTemplateSurveyImport.php:59`). `ImportAllLanguageStrings` re-reads the workbook once per translated column and matches each row by linear scan. A failure between sweep and flag reset leaves flags true, so the next sweep skips stale rows. Null derefs on an unmatched module or list (`I/GetsModuleNamesPerRow.php:24-27,33-44`). | With two queue workers a select question fails the import with "id on null". A large multilingual template times out. | codex B15, #139, #140; cost and flag residue new |
| F16 | Medium | `RequiredMedia`: insert from the choice-list import omits NOT NULL `type` (`I/XlsformTemplateChoiceListImport.php:67-72`, `mig/011:21`), and a test asserts the throw; its sweep flag is never reset, so obsolete media are never removed (`M/XlsformTemplate.php:380-391`); null deref for a module version with no module (`:64`). | A host-built local module using `select_one_from_file` crashes the import. Removed attachments stay "required" forever. | New |
| F17 | Medium | `FinishChoiceListEntryImport` detects localisable lists by `whereLike('properties', '%localisable": true%')` (`:31`), which matches MySQL's JSON text form and never SQLite's. Hard-codes `location` and `conversion_rate` (`:35,56-60`). Calls `requiredDataMedia()` on a module version, which has none (`:81`). | Works in production, cannot be tested in the suite; a standalone module upload with a localisable list fatals after partial writes. | codex A7, B17; SQL text match new |
| F18 | Medium | `generateXlsfile()` deletes and recreates the template's shared entity-list rows on every team deploy (`M/Xlsform.php:398`, `M/XlsformTemplate.php:292-305`) and builds the path from the raw title. | Two teams deploying at once: one build finds no entity lists and omits the `entities` sheet *(inferred)*. A title containing `/` breaks the path. | New |
| F19 | Medium | Choices export ignores removal-from-context, uses MySQL-only `json_keys` (`E/XlsformExport/XlsformChoicesExport.php:153`, `XlsformSurveyExport.php:258`, `E/ChoiceListAsMediaAttachmentExport.php:60`), and queries strings per row, locale and field. | A choice a team hid still appears in its form. No end-to-end build can run in the test suite. | codex B18, B19, C1 |
| F20 | Medium | Translations export emits every per-module copy of each entry; sorts by a non-existent `row_number`; `$template->locales` fatals for a template with no modules (`M/XlsformTemplate.php:159-170`); row identity is the surrogate id, which F1 and the `(version, name, type)` survey key make unstable. | A translator sees the same choice three times; a file returned after a re-import no longer matches. Changing a question's type drops its translations. | New |
| F21 | Medium | `DeployDraftXlsformToOdkCentral` accepts a template but calls `xlsformVersions()`, which `XlsformTemplate` lacks (`:84`). | `XlsformTemplate::deployDraft()` uploads the draft three times, then reports failure. No caller in the two packages; latent for hosts. | New |
| F22 | Medium | Impure accessors: `enketoDraftUrl` does HTTP and a write (`M/Abstracts/HasXlsformDrafts.php:136-151`); `liveSubmissionsCount` does HTTP; `status` queries; `Locale::status` loads all forms. | A table listing forms makes one ODK call per row. | package-review 1.3 |
| F23 | Medium | Missing uniques and FKs: `choice_list_owner`, `choice_list_entries_removed_owner`, `language_owner` have no unique pair; no "one default version per module"; `required_media` has no unique `(template, name)`; `xlsforms.has_draft`/`is_active` are strings; `mig/027:20` `default(0)` on an FK. `locale_owner` and `locales.processing_count` are written by nothing in the core. The core seeds no languages or string types, and with empty tables no column is recognised as translatable. | Double-click on "hide choice" attaches twice, and the toggle then needs two clicks. A fresh install deploys label-less forms with no error. | Partly known; rest new |
| F24 | Low | Dead or broken code: `XlsformTemplateModuleTypeImport`; `Locale::getStatusForFormTemplate/getStatusForForm` (call three methods that do not exist); `Xlsform::locales()`; `XlsformModuleVersion::country()`; `ChoiceListEntry::model()`; `IsLookupList`; empty traits `PublishesToOdkCentral`, `HasUploadedXlsformFile`; the `NotifyUserThat…` jobs; a commented 409 block (`OdkFormService.php:81-102`). `expandMediaColumnHeaders` exists three times. | Maintenance cost; the broken `Locale` methods fatal if a host calls them. | Partly package-review §2 |
| F25 | Medium | Test gaps. No test imports a real workbook through the whole chain, none re-imports, none covers `cloneForOwner`, the choices or survey export queries, the language-string import, the deploy and publish jobs, `publishForm`, or two owners in one template. Fixtures insert with `DB::table` to avoid the hooks (`tests/Pest.php:22-27`), so the hook paths are untested by design. Two tests assert defects as behaviour. | Every critical finding above is invisible to CI. | codex C9 |

### Status of earlier findings in this scope

| Prior id | Status on this branch |
| --- | --- |
| package-review 1.1, 1.2, 1.3 | Still present (F14, F22). |
| package-review 1.5 | Fixed for imports and roles. Residual host specifics in `J/FinishChoiceListEntryImport.php:35,56`. |
| package-review 1.8 | Still present. |
| package-review §2 "`isDirty` inside `saved` never fires" | **Refuted.** Laravel fires `saved` before `syncOriginal()`, so the check is true there. The real defect is the opposite: `afterXlsformFileUpdated()` runs twice on create (`M/XlsformTemplate.php:66-68` and `:73-75`). |
| package-review §2, other | `[A-z]` regex, update-then-save, `abort(500)` in services: still present. |
| package-review B4, B8, B14, B17 | Fixed. |
| package-review B11 | Null deref fixed; the mechanism remains (F4). |
| package-review B12, B13 | Still present (`src/Services/UpdateXlsformTitleInFile.php:44,58`; `J/PrepareSurveyRowPaths.php:72`). |
| Issues #135, #138–#141 | Still present. |
| codex A1, A2, A3 | Still present (F5, F6, F14, F11). |
| codex A5, A7 | Partly fixed (F13, F17). |
| codex C1, C2, C3, C8, C9 | Still present. C5: model scanning fixed, command auto-registration still present. |
| codex B3, B4, B7, B8, B10–B15, B17–B19 | Still present. |
| codex B16, B21 | Fixed. |

### Keep, rewrite, delete

| Item | Verdict |
| --- | --- |
| `XlsformTemplate`, `Xlsform` models | Rewrite as thin models: relations, casts, media collections. Remove `booted()` logic, accessors with I/O, and the deploy, publish and sync methods. |
| `XlsformModule` | Keep, add a stable `key`; drop the `created` hook. |
| `XlsformModuleVersion` | Rewrite as a module variant with an explicit kind; delete `cloneForOwner`. |
| `ChoiceList`, `ChoiceListEntry` | Rewrite for the canonical schema; delete the global scope and hooks. |
| `SurveyRow` | Keep columns; delete hooks and the boolean cast on `required`. |
| `LanguageString`, `LanguageStringType`, `XlsformModuleVersionLocale` | Replace with one translations table. |
| `Locale`, `Language` | Rewrite small. |
| `XlsformVersion` | Replace with a form build record. |
| `HasXlsformDrafts`, `HasLanguageStrings`, `IsLookupList`, empty traits | Delete. |
| `src/Imports/XlsformTemplate/**` | Rewrite as one parser, validator and applier. Keep the module-grouping rule and its tests. |
| Import-chain jobs, `AddMissingChoiceListStrings`, `LinkModuleVersionToLocales`, `Finish*` | Delete; one import job replaces them. Keep the path algorithm from `PrepareSurveyRowPaths` and its tests. |
| `src/Jobs/XlsformDeployment/*` | Rewrite as one deploy job and one publish job. Keep the retry classification. |
| `HandleXlsformTemplateAdded`, media-event wiring | Delete; the upload calls the import action. |
| `src/Exports/XlsformExport/**` | Rewrite to render a resolved form from memory (no per-cell queries, no `json_keys`). Keep styling and the entities sheet. |
| Translations export | Rewrite on natural keys; keep styling and protection. |
| `OdkFormService`, `OdkFormMediaService` | Keep the HTTP calls; remove the Eloquent writes. |
| `UpdateXlsformTitleInFile` | Keep, fix package-review B12. |
| Core migrations `001–009, 011, 021, 024–032, 034` | Replace with one fresh set. |

## Part 2. Core, post-publish half and ODK transport

Everything after "a form is published": the ODK Central client, provisioning, polling, pulling, storing, projecting submissions into entities, media, data export, commands.

Paths in this part: `SubSvc` = `packages/odk-link-core/src/Services/OdkLinkServices/OdkSubmissionService.php`, `FormSvc` = `…/OdkFormService.php`, `MediaSvc` = `…/OdkFormMediaService.php`, `ProjSvc` = `…/OdkProjectService.php`, `DatasetSvc` = `…/OdkDatasetService.php`, `Models/` = `packages/odk-link-core/src/Models/OdkLink/`. The core suite was run: 239 passed. Findings marked *(inferred)* rest on ODK Central's documented API behaviour and were not tested against a live server.

Three things to know first:

- **This half has no consumer in the monorepo.** Nothing in the Filament package or the reference app calls publish, pull, export, submission edit or any dataset or entity API. There is no schedule entry for the poll. It can only be exercised through tinker or a harness.
- **It cannot run on a fresh install** (P7, the same schema gap as F9).
- **Most of it needs targeted fixes, not a rewrite.** The exception is the pull → store → media → project chain (about 300 lines in `SubSvc` plus the `Submission::updating` hook), which is wrong in shape and should be rebuilt as staged, idempotent jobs.

### Flows as they are

| Flow | Call chain |
| --- | --- |
| Token | `OdkLinkService::authenticate()` (`src/Services/OdkLinkService.php:37`) caches `odk-token` for 20 hours. `forgetToken()` is called from one place only (`FormSvc:76`). Every other call is `Http::withToken()->…->throw()` with no timeout, retry or 401 handling. |
| Provisioning | Owner `created` event (`Models/Traits/HasXlsformTemplates.php:26`) → `ProjSvc::createProject()` → local `OdkProject` → `createProjectAppUser()` (`ProjSvc:44`, then `POST assignments/manager/{id}` at `:61`) → `app_users` row holding the token. All synchronous inside the request that creates the owner. |
| Publish | `Xlsform::publishForm()` → `PublishXlsformOnOdkCentral` → `FormSvc::publishForm()` (`:235`): publish on Central, then in a transaction deactivate versions, copy schema and file to a new `XlsformVersion`, soft-delete draft submissions → redeploy the same file as a new draft, which overwrites `odk_version_id` with the draft version. |
| Poll and ingest | `odk:poll-for-odk-data` → `PullSubmissionsFromXlsform` → `SubSvc::getSubmissions()` (`:113`): load all local submissions, `GET` the whole submission list, diff, `GET` the whole OData collection with `$expand=*`; per entry look up the version (throws if missing, `:179/:182`), `updateOrCreate` by `odk_id` (`:185`), dispatch `ProcessOdkSubmission` (`:199`), then fetch media (`:201`). The job projects into entities (`SubSvc:303` onward) and then calls the host's `SubmissionProcessor`. |
| Edit, by poll | Same, but `updateOrCreate` fires `Submission::updating` (`Models/Submission.php:63-77`), which deletes entities and re-projects synchronously; then the queued job projects again. |
| Edit, by Enketo | `Submission::editOnEnketo()` → Enketo → `routes/web.php:7` (no middleware) → `SubmissionController::update` → `SubSvc::updateSubmission()` (`:77`). No host processor, no media refresh. |
| Entity lists | To Central: nothing. `DatasetSvc` (create dataset, properties, entity CRUD, bulk create) has no callers and no tests. From Central: nothing. The only link is `MediaSvc::linkEntityListAttachments()`, which logs a warning on 404. |
| Data export | `SubSvc::exportAsExcelFile()` (`:512`) → `SurveyExport` (all submissions and entities in memory). No caller in the repo. |

### Findings: security and cross-owner leakage

| ID | Sev | Evidence | Scenario and effect | Prior id |
| --- | --- | --- | --- | --- |
| P1 | High; critical if confirmed *(inferred)* | `ProjSvc:61`; token in the QR code (`Models/AppUser.php:41`) | The project app user is given the project **manager** role, and its token is in the QR code every enumerator scans. If Central honours role verbs for `/v1/key/{token}/…`, anyone holding the QR can read and export all the project's submissions and alter or delete its forms. Check: `GET /v1/key/<token>/projects/<id>/forms/<form>/submissions` must return 403. | New |
| P2 | High | `routes/web.php:7`; `SubmissionController.php:14,20`; `SubSvc:90-107` | A guest GET to `/odk/submissions/{id}/update` (integer ids) calls Central with platform credentials and mutates data. For a never-edited submission it writes a null `updated_at` and then 500s. With no `web` middleware the session never starts, so the legitimate user always lands on `/` instead of the return URL. | codex B2; extra effects new |
| P3 | High | `src/Exports/DatasetAsMediaAttachmentExport.php:31,33`; `Models/DatasetVariable.php:40`; `Models/EntityValue.php:18`; `Models/Entity.php:83-94` | The lookup CSV for owner A's form is built from every entity in the dataset, so owner B's records are uploaded to A's Central project and reach A's devices. Separately, two relations join on variable name only, so any two datasets sharing a variable name (`name`, `age`) return each other's rows. | codex B1; name-only join is a residue of package-review B16 |
| P4 | Medium | `Models/Submission.php:46`; `Models/Xlsform.php:88`; no scope on `Entity`, `EntityValue`, `Dataset`, `OdkProject`, `AppUser` | Isolation is an ambient global scope on two models. Queue, CLI and any panel without a tenant are unscoped. Any host query on entities is cross-owner unless it adds its own filter. | codex A5, partly addressed |
| P5 | Medium | `SubSvc:52-57` | Attachments are written to the media disk root under Central's bare filename before the media library moves them. Two pulls with the same filename can attach one submission's file to another. A re-pull of an edited submission adds every attachment again. | New |
| P6 | Low | `Models/AppUser.php:14`; `src/Services/OdkLinkService.php:28` | Serialising an owner's project emits the app-user token. The session-token cache key is not keyed by endpoint or account, so a changed `ODK_URL` keeps the old token for up to 20 hours. | codex C8 |

### Findings: data integrity and idempotency

| ID | Sev | Evidence | Scenario and effect | Prior id |
| --- | --- | --- | --- | --- |
| P7 | Critical | No migration for `xlsform_versions.is_draft`; `dataset_variables.order` and the unique `(name, dataset_id)` the upsert at `Models/XlsformTemplate.php:502-508` needs; `datasets.primary_key`; `entities.uuid`; `datasets.choice_list_id`; `dataset_parents.foreign_key_variable_id`; `entity_values.odk_project_id`. `016:16` has the `nulllable()` typo. | Fresh install: the first draft deploy fails writing `is_draft`, after Central already has the draft. Linking a section to a dataset fails on `order`. Parent keys and entity uuids silently come out null. | codex B7; other columns new |
| P8 | High | `Models/Submission.php:63-77`; `SubSvc:185,199,321,409`; `ProcessOdkSubmission.php:34` | A submission edited on Central has its entities rebuilt in the `updating` hook and then created again by the queued job, so its entity tree is doubled. Any retry of the job adds another tree, because projection never deletes first and has no transaction. Two overlapping pulls dispatch the job twice. | codex B5; retry and overlap variants new |
| P9 | High | `SubSvc:310,371,419,483` | Projection reads the template's current sections, not the version the submission was made with, although `xlsform_versions.schema` is stored at publish and never read. After a re-import that renames or removes a question, old-version submissions lose those values. Owner-only module questions are always dropped. A root section with no dataset null-derefs at `:323`, so every submission for every form of that template fails. | codex A4; issue #69 |
| P10 | High | `SubSvc:521-527` | `select_multiple_from_file`, `select_multiple … or_other`, or a list present in the template but not in the owner's selected versions: the job fatals for every submission that answers the question. The submission row stays saved and is never re-projected. | #136; wider than recorded |
| P11 | High *(inferred)* | `SubSvc:348,446` (`! is_array($value)`) | OData returns geopoint, geotrace and geoshape as GeoJSON objects unless `$wkt=true`, so every location answer is dropped from entities and exports. It survives only in `submissions.content`. | New |
| P12 | High | `SubSvc:38-57,199-201` | (a) Collect uploads attachments after the submission; if the poll sees none or some, the row is marked ingested and later attachments are never fetched. (b) A missing attachment 404s and aborts the job *(inferred)*. (c) Any 5xx or timeout during download has the same result. (d) The host processor can run before media exists. | (c) codex B6, (d) package-review B15 / #134; (a), (b) new |
| P13 | High | `SubSvc:165-183` | One submission whose `formVersion` has no local row (form published or edited directly on Central, duplicate publish, restored database) throws inside the loop. Every later poll throws at the same entry and nothing after it is ingested. The behaviour is asserted by a test. | New |
| P14 | Medium | `SubSvc:115` vs `:185`; `src/Jobs/PullSubmissionsFromXlsformQuietly.php:54,74` | A locally soft-deleted submission that is edited on Central hits the unique `odk_id` and the pull fails on every poll. `odk:get-submissions-quietly` stores rows with no version id, so the next normal poll treats all of them as edited and double-projects. | codex B9; second part new |
| P15 | Medium | `SubSvc:130-142` | Submissions deleted on Central are never removed locally. Review state (rejected, has issues, approved) is not stored, so the host cannot exclude rejected data. | New |
| P16 | Medium | `SubSvc:92,99,107,189`; `Models/Submission.php:69` | Timezones: with an app timezone other than UTC, `updateSubmission` shifts its filter window, the result is empty, `content` is set to null and the hook deletes all entities before a TypeError. Every Enketo edit then destroys that submission's projection. | New |
| P17 | Medium | `Models/Xlsform.php:83-86`; migration `010:18` | Deleting an `Xlsform` with submissions deletes the form on Central first, then the database delete fails on the foreign key. A soft-deleted owner makes the pull fatal, and its `failed()` handler too, so no notification is sent. | #135 family |
| P18 | Medium | `FormSvc:245-303`; no unique `(xlsform_id, version)` | Publishing with no draft on Central creates another version row with the same version string each time; new submissions attach to the oldest, inactive row. After every publish `odk_version_id` holds the draft version and `live_needs_update` is true again. | codex B8; rest new |
| P19 | Medium | `PollForOdkData.php:28`; `FormSvc:331` | Archiving sets `is_active=false`, so the form is never polled again. Submissions uploaded between the last poll and the archive are never ingested. | New |

### Findings: robustness and performance

| ID | Sev | Evidence | Scenario and effect | Prior id |
| --- | --- | --- | --- | --- |
| P20 | High | Every `Http::withToken()->throw()` site; the two ingestion jobs have no `$tries`, `$backoff`, `$timeout` or unique lock | A 401 from an invalidated session leaves the dead token cached for up to 20 hours; every poll fails. Laravel's default 30-second HTTP timeout applies to the full download. A failed `ProcessOdkSubmission` is never re-attempted. One shared cause produces one persistent notification per submission per recipient. Errors are a mix of five exception styles including `abort(500)`. | Partly fixed |
| P21 | Medium | `PollForOdkData.php:28-34` | No overlap protection: a poll scheduled faster than a pull completes gives concurrent pulls. The package neither schedules nor documents the poll. No `ODK_URL` guard. | New |
| P22 | Medium | `Models/Traits/HasXlsformTemplates.php:26-72` | A Central error during owner creation leaves an owner with no project, or a project with no app user, and every `owner->odkProject->id` site fatals. No repair command; a naive retry creates a second Central project. | #135, #147 |
| P23 | Medium *(inferred)* | `MediaSvc:105-156` | A form that consumes an entity list not yet on Central is published with the attachment missing, and the select is empty on devices. Only a log line records it. | New; #112 related |
| P24 | Low | `config/odk-link.php:121-122`; `MediaSvc:49,76` | The misspelled storage config key always falls back to `local`; local-path assumptions break on S3. | codex B20, C8, B19 |
| P25 | High | `SubSvc:115,130,145,157,166` | Every poll loads every local submission with its full content, downloads the whole list and the whole OData collection, then matches O(n²). For each new entry it reloads every submission of that version. A large form eventually hits the timeout or memory limit before the loop starts and then never ingests again. | package-review 1.9, codex C2; reload is new |
| P26 | Medium | `SubSvc:334-342,419-427`; `Models/Xlsform.php:346`; `src/Exports/SurveyExport.php:23` | An unwrapped `orWhereNull` loads every global choice entry in the database once per repeat instance. An accessor downloads all submission metadata to count them. Exports load everything. | #137, #142, #143 |

### Findings: maintainability

| ID | Sev | Evidence | Note | Prior id |
| --- | --- | --- | --- | --- |
| P27 | Medium | `src/Services/OdkLinkService.php:21-26` | Six stateful traits forming one class of about 1,700 lines. `SubSvc` alone does fetch, media, persistence, projection and export. `getOneSubmission` is a drifted copy of `getSubmissions` with no caller; `PullSubmissionsFromXlsformQuietly` is a third copy. Two CSV lookup generators, two multi-select expanders. | package-review 1.4, 1.8; codex A6, C4 |
| P28 | Medium | `src/OdkLinkCoreServiceProvider.php:75` | Every file in `src/Commands` is auto-registered in every host; see below. | codex C5 |
| P29 | Low | — | Dead or host-specific code: all of `DatasetSvc`, `OdkDataset`, `app_user_assignments`, `HasOdkCentralAccount`, `OdkUserService`, `authenticateAsUser`, `xlsforms.has_latest_media`, several config keys. | Partly package-review §2 |

Commands:

| Command | State | Recommendation |
| --- | --- | --- |
| `odk:poll-for-odk-data` | Works; no lock, no guard | Keep, fix |
| `odk:get-submissions-quietly` | Causes duplicate projection later (P14) | Delete with its job |
| `odk:trs` (`TestRemoveSub`) | Gated to `local`, but picks the form by title globally and flushes the whole cache | Delete |
| `odk:test-csv-media-generation` | Broken: calls a method that does not exist | Delete |
| `app:generate-submissions` | Not environment-gated; posts synthetic submissions to a live form | Move to dev tooling (#178) |
| `app:update-xlsform-drafts` | `app:` prefix collides with the host's namespace | Rename |

### Status of earlier findings in this scope

| Prior id | Status on this branch |
| --- | --- |
| package-review 1.3, 1.4, 1.8, 1.9 | Still present. |
| package-review B1, B3, B6, B9, B10, B14, B18 | Fixed (B1 leaves `odk:trs`). |
| package-review B7, B16 | Fixed as written; B7 depends on a missing column (P7), B16 leaves the name-only join (P3). |
| package-review B15 / #134 | Still present. |
| Issues #135, #136, #137, #142, #143, #147 | Still present. |
| "Token never invalidated" | Partial: one call site. |
| codex A4, B1, B2, B5, B6, B7, B8, B9, B19, B20, C2, C4, C6, C7, C8 | Still present. |
| codex A5, A6, A7, C9 | Partial. |

### Test coverage

| Area | Covered | Not covered |
| --- | --- | --- |
| Token, project, user, form services | Happy-path URL shapes with `Http::fake` | `createDraftForm`, `publishForm`, `updateSchema`; any error status |
| Pull | New, already ingested, unknown version throws (3 tests, hand-written payload) | Edits, trashed, drafts, attachments, pagination, Central errors, overlap, two owners |
| Projection | One test with one root field | Repeats, select_multiple with a real list, geo, version mismatch, re-projection, retry |
| Controller | Two tests that assert the unauthenticated behaviour as correct | Auth, null `updatedAt`, timezone |
| Provisioning hook, `DatasetSvc`, `SurveyExport`, dataset CSV | Nothing | All of it |
| Database | SQLite only | MySQL; the missing columns go unnoticed because tests never run deploy or publish |

No test uses a recorded real Central response.

### What is fix-in-place and what is not

| Area | Verdict |
| --- | --- |
| Transport (token, timeouts, retries, one exception type) | Small extraction; mechanical |
| Provisioning, publish hand-off, media upload, exports | Targeted fixes |
| Pull → store → media → project | Restructure |
| Schema | Part of the consolidated migration set |
| Entity and dataset sync | Nothing to restructure. Decide: wire it or delete it. |

## Part 3. Filament package and reference app

### Inventory

| Piece | What it does | Domain logic that belongs in the core? |
| --- | --- | --- |
| `OdkLinkAdmin` plugin | Discovers all resources and widgets. No options, no replacement map (`F/src/OdkLinkAdmin.php:22-30`). | No |
| Service provider, `FilamentCurrentOwnerResolver`, `FilamentOperationNotifier` | Registers the core first, binds the two adapters. Sound and tested. | No |
| `HtmlBlock`, `RedirectsToListAfterSave` | Small helpers. | No |
| `ClickableLink`, `DraftTestingQrCode`, `TestsFilamentOdkLink`, 7 views | Dead. | Delete |
| `XlsformTemplateResource` | Template CRUD shell; owner is `Platform::first()` (`:32`). | Owner resolution should be a seam |
| `CreateXlsformTemplate` | Four-step wizard. Step 1's `afterValidation` validates, tests on ODK, saves and redirects (`:66-151`); steps 2–4 are empty. | **Yes, all of it** |
| `ViewXlsformTemplate` | Infolist; Make Available, Replace XLSForm, Edit, Delete. | **Yes** (replace, make available) |
| `EditXlsformTemplate` | Wizard: media, datasets, sections; calls `extractSections()` in `afterSave`. | **Yes** |
| `XlsformTemplateForm`, `XlsformTemplateInfoList` | Upload fields, media repeaters, dataset links, section mapping. The infolist renders through `enketo_draft_url`, an accessor that calls ODK and writes. | Entity-list exclusion rule |
| `XlsformTemplateTable` | List; inline `available` checkbox; Replace action; bulk delete. | **Yes** (third replace copy) |
| `XlsformModuleRelationManager` | Modules of a template: create, edit, delete. | Should not be raw CRUD |
| `ChoiceListResource` + entries relation manager | Lists every per-version copy; entry CRUD with one label per locale. | Shaped by the per-version copy model |
| `XlsformModuleResource` | List and modal-edit modules; `name` (the import identity) is editable. | Renaming is an identity change |
| `XlsformModuleVersionResource` | Lists all versions including team clones; create and edit with file upload. | Yes: variant kinds and import |
| `DatasetResource` + 3 relation managers | Dataset CRUD; two relation managers expose create, edit and delete **on templates**. | Should be read-only queries |
| 2 widgets | Static text. | No |

### Findings

| ID | Sev | Evidence | What a user sees |
| --- | --- | --- | --- |
| U1 | High | `R/XlsformTemplates/Pages/CreateXlsformTemplate.php:129-134` (`throw $e; // TEMP`; `:135-148` unreachable) | Any failure that is not a Laravel validation exception gives a 500 page: no `ODK_URL`, ODK down or 401, a workbook with no `survey` sheet, an empty sheet, no file chosen. |
| U2 | High | `R/XlsformTemplates/Schemas/XlsformTemplateForm.php:60-68` | The upload field has no `required()`, accepted types or size limit. With no file the View action calls a method on null, an `\Error` that `catch (\Exception)` misses. A `.csv` or `.docx` goes straight to the Excel reader. |
| U3 | High | Three replace paths: Create `:66-151`; View `ViewXlsformTemplate.php:47-106`; List `Tables/XlsformTemplateTable.php:47-88` | The list action is broken (#124). The list has a processing guard and no pre-validation; the view has pre-validation and no processing guard. The view rethrows only the first of several errors, prefixed "ODK Returned the following error:". Replace allows a title change that the edit form forbids. |
| U4 | High | No lock in the UI. View action, Edit, Delete and bulk delete are all reachable while `processing`. | Replacing during an import, or two admins replacing at once, runs two import chains over the same rows; the first to finish shows "ready" while the second is still writing. Delete during an import leaves jobs running against a deleted model. |
| U5 | High | `R/Datasets/Tables/XlsformTemplateSourcesRelationManagerTable.php:31-41`, `XlsformTemplatesRelationManagerTable.php:28-38` | On **Edit Dataset**, the "Populated by…" and "Used in…" tabs have Delete and bulk delete that delete the **template itself**, not the link; the `deleting` hook then removes the ODK form and the modules. "New" creates a title-only template with no file or owner. |
| U6 | High | No policy, `canAccess()` or `->authorize()` anywhere in `F/src`. Filament allows when no policy exists. | Anyone who can enter the panel can delete templates. Groundswell hand-wrote six policies for package models; even then the custom actions "Replace XLSForm" and "Make Template Available" are not gated. |
| U7 | High | Every delete entry point (template, module, module version, dataset, entry) | No in-use check and no "N team forms, N submissions affected" confirmation. Template delete calls ODK synchronously, so a bulk delete stops half-way when ODK is down. Module and version deletes cascade and silently strip the module from team forms without marking them stale. The default "Global" version is deletable. |
| U8 | High | `R/ChoiceListResource.php:59-95`; `template.title` (`:63`) names a relation `ChoiceList` does not have | One row per (module version, list), including every team clone, with the only distinguishing column blank *(inferred on render)*. The admin sees N identical `crop_list` rows; a change applies to one copy only. |
| U9 | Medium | `ChoiceListResource.php:30-39` | "New choice list" exists with a non-required select on a NOT NULL column, so a 500. `can_be_hidden_from_context` is shown and filterable but cannot be set, so there is no UI to enable "remove from context". |
| U10 | Medium–High | `ChoiceListResource/RelationManagers/ChoiceListEntriesRelationManager.php` | With no current owner in the admin panel the owner scope is off: every team's private entries are listed with the global ones, unlabelled and editable. `name` has no unique or format rule and the database unique does not fire for a NULL filter, so duplicates are created silently. No `cascade_filter` field. Renaming an entry changes the stored value for existing data with no warning. |
| U11 | Medium | `XlsformTemplateForm.php:183` | The "required dataset" select compares the template owner's id (a Platform id) with team ids: global datasets are never offered, and the team whose id equals the platform id has its datasets offered. |
| U12 | Medium | `R/Datasets/Schemas/DatasetForm.php:29-47` | The unique-name rule is scoped by tenant only. In a tenant panel `owner_id` is a hidden field, client-tamperable and not re-checked. |
| U13 | Medium | Create, list, view | After create: "XLSForm Template **Updated** … saved successfully" while the import is only queued. No processing column, no failed or progress state, refresh depends on Echo with no polling fallback, and with no queue worker it is "processing" forever. Failure notifications go to the database, but the reference panel does not enable database notifications. |
| U14 | Medium | `XlsformTemplateTable.php:34-37`; `ViewXlsformTemplate.php:40-46` | "Available" is an inline checkbox and an unconfirmed button with no precondition. It creates a form for every owner inside the request. Unticking withdraws nothing. |
| U15 | Medium | `XlsformTemplateInfoList.php:112,145,238-239` | Rendering the View page calls ODK and writes to the record. With no `ODK_URL`, or a template with no sections, the page cannot render. |
| U16 | Medium | `EditXlsformTemplate.php:42-95` | Edit step 1 is an empty step telling the user to upload a file it hides. Every media upload is required, so partial progress cannot be saved. Saving media marks the draft stale, but nothing in the UI refreshes it. |
| U17 | Medium | `R/XlsformModuleVersions/…` | Lists every version including all team clones, with no owner or kind column. A relation name is wrong, so the template column is blank. The upload is always shown, so an admin can upload a file onto the default "Global" version and start an import. |
| U18 | Low–Medium | Module forms | Module `name` is freely editable. "New" on the template's module tab offers only a label; `name` is NOT NULL, so a 500. |
| U19 | Medium | Both widgets | Discovered onto every dashboard. "ALERT … no valid connection to ODK Central" shows even when ODK is configured, and only tests for a blank URL. The other widget addresses team users on the admin dashboard. |
| U20 | Medium | `XlsformTemplateResource.php:30-33` | With no `Platform` row (the seeder skips it without `ODK_URL`), creating a template violates a return type. |
| U21 | Low–Medium | `CreateXlsformTemplate.php:35,66,153-161` | Clicking **Next** on step 1 creates the template; Cancel afterwards leaves it created. |
| U22 | Medium | `XlsformTemplateForm.php:75,104,157`; `XlsformTemplateInfoList.php:253-254` | Unescaped `HtmlString` built from the media filename, the template title and the whole error bag, which includes column headers from the uploaded workbook and ODK error text. HTML injection by anyone who can upload. |
| U23 | Low–Medium | Column views, relation manager, infolist | N+1 on the template list (four counts per row); a query per locale; one 30-line action copied four times. |
| U24 | Low | `F/src` generally | About 170 English literals, no language files. Groundswell translates through gettext `t()` and translation.io, not Laravel key files. |
| U25 | High (coverage) | `F/tests` (17 tests), `apps/reference/tests` | No test executes any action: upload, replace, make available, delete, entry CRUD. No test renders the template View or Edit page. No authorisation test. U5, U8, U10 and U17 would each fail the first render or action test written. |
| U26 | Low | `F/src/Support/*` | Notifications need the host panel to enable database notifications and broadcasting; nothing documents or checks that. |

### Status of earlier findings in this scope

| Prior finding | Status |
| --- | --- |
| package-review §1.6 (85-line closure; duplicated replace flow) | Still present; the copies have since diverged (U3). |
| package-review §1.7 (no seams, no translation layer) | Still present. |
| package-review B2, B5 | Not applicable (`OdkTeam` deleted; the gap is now U6); fixed. |
| codex: no UI replacement contract; TEMP rethrow | Still present. |
| codex: jobs embed Filament notifications and role names | Fixed in step 4. |
| #124 | Still present. |
| `Platform::first()` | Still present. |
| Reference README: `rootSection->schema` null; `bool` closure returns null | Still present. |
| `DatasetTable` route | Fixed (PR #179). |
| Step 7 plan commit-1 items | All still to do. |

### The stubs question

Evidence from the one real host:

- Groundswell changed **zero** admin resource files. It registers `OdkLinkAdmin::make()` unmodified (`GS:app/Providers/Filament/AdminPanelProvider.php:74`).
- What it added around the admin UI: six policies, a `Gate::before`, and an 87-line relation manager on its own `TeamResource`.
- Its tests import the package's page classes.
- All its real UI work is team-facing and deeply branded: custom Blade pages, gettext, completion flags on `Team`.

What follows:

- For the admin panel, publishing 42 files into a host that customises none of them turns a Composer upgrade into a manual merge and gives the host nothing. The roughly 25 UI defects above would each need hand-merging into every host after publication.
- The needs that do exist (authorisation, owner resolution, navigation, a hook after template save) are configuration.
- The earlier argument against keeping the plugin ("two live copies of the UI") goes away if publishing is per resource and the plugin skips what the host replaced.
- This agrees with the Codex review's position and reverses the recorded decision "`OdkLinkAdmin` deleted in step 7". It is a decision for the product owner; see the target design, decision D1.

Per piece:

| Piece | Recommendation |
| --- | --- |
| Provider, adapters, `HtmlBlock`, CSS, translations | Runtime library |
| Template resource, one Replace action, Make Available, import status | Runtime, with the actions exposed as public classes. This is where bugs must be fixable by `composer update`. |
| Choice list resource (rewritten on the canonical schema) | Runtime |
| Module and module-version resources | Runtime, reduced to read-mostly views inside the template. Drop the standalone raw-CRUD module resource. |
| Dataset resource | Runtime; the two template relation managers become read-only |
| `OdkUrlAlertWidget` | Runtime with `canView()` |
| `AvailableOdkTemplatesWidget`, dead views and classes | Do not ship |
| Team-facing UI | Runtime components and actions the host composes into its own pages; see below |

Escape hatch, opt-in: a publisher that writes **thin** stubs (a class extending the package class, about ten lines), so drift is near zero by construction.

### Team-facing UI

Groundswell's team-facing code, with what is generic:

| Groundswell piece | Lines | What is generic | What the package should ship |
| --- | --- | --- | --- |
| Choice entries for teams (resource, page, widget, sibling service) | 542 | Add, edit, delete own entries; remove global ones from context; labels per locale. The sibling service disappears with canonical lists. | A table component with form schema and three actions (about 250 lines) |
| Translations (pages, Livewire forms, import, inspector, counter, notify jobs) | ~1,650 | Import, inspection, counter, notify (~485) are core. Locale selection and download, upload, review (~700) are generic behaviour. | Core import; locale table, download and upload actions, status column |
| Initial Pilot (page, deploy job, team claim methods) | ~750 | Draft state per form, "update test forms", QR and Enketo links, polling | A draft-forms component and a deploy action (about 250 lines) |
| Question-text editing (HDDS hints, informed consent) | ~720 | The mechanism: give the owner a variant, edit row text per locale | Core actions; a text-editor component parameterised by module and row filter |
| Optional modules | 354 | Select, reorder, remove modules on a form | Core action; a picker component |
| Admin owner-forms relation manager | 87 | Entirely | A relation manager the host attaches to its own owner resource |
| Submissions (resource, tables, monitoring) | ~1,140 | List and view, edit on Central, forms table with publish and pull (~550) | Tables and an infolist schema, after the post-publish work |

- Total about 5,200 lines, of which roughly 2,400 are generic: about 500 belong in the core and about 1,900 are UI building blocks.
- Full-page stubs would not survive Groundswell's branding. Runtime components taking the owner explicitly from the host page would.
- The package deleted its earlier team UI as unsafe. The guard against repeating that: components never read an ambient owner, and each has an "other tenant gets 403/404" test in the reference app.

### The reference app as a test host

Today: one user who can do everything, an empty team panel, no database notifications, no fixture workbook, SQLite only. To be a credible host it needs:

- Fixture workbooks (valid, replacement, several invalid) and a reusable fake ODK Central so UI tests run upload → import → view → replace → publish.
- A team panel with one plain page per team component, doubling as the example of how a host composes them.
- A host `TeamResource` in the admin panel carrying the owner-forms relation manager; database notifications enabled with polling.
- Seeded users for platform admin, Team A, Team B and no rights; an authoriser bound; tests that a team user gets 403/404 on admin resources and on the other team's data.
- A seeder that imports a template through the real pipeline and gives Team A a private entry and a removed entry, so owner-leak cases have something to leak.
- A MySQL CI job (three migrations fail there today).

## Part 4. Executed scenario (B3)

### What was run

`packages/odk-link-core/tests/Unit/Scenarios/PostPublishIngestionScenarioTest.php`: twelve steps, each asserting the **correct** behaviour. ODK Central is faked at the HTTP layer with a stateful fake, so a test can change what Central "holds" between polls. Everything from the pull onwards is the real code, with the queue running inline.

- Default run: the nine steps that fail today are skipped, each naming its finding, so the suite stays green (core: 242 passed, 9 skipped).
- `ODK_SCENARIO_RUN_KNOWN_DEFECTS=1 vendor/bin/pest tests/Unit/Scenarios` runs all twelve. Result on 2026-11-27: 3 passed, 9 failed, each for the predicted reason.
- When a finding is fixed, its `skip` is removed and the step becomes a regression test.

| Step | Correct behaviour | Result | Finding |
| --- | --- | --- | --- |
| 1 | A new submission is stored and projected into one entity | Pass | — |
| 2 | Polling again changes nothing | Pass | — |
| 3 | An answer of zero is kept | Pass | — |
| 4 | Editing a submission on Central replaces its entity | **Fail**: 2 entities | P8 |
| 5 | A retried processing job projects once | **Fail**: 2 entities | P8 |
| 6 | One submission with an unknown form version does not stop the others | **Fail**: the valid submission is not ingested | P13 |
| 7 | A location answer is stored | **Fail**: no value | P11 |
| 8 | A submission deleted on Central is removed | **Fail**: still present | P15 |
| 9 | Polling continues after a locally deleted submission is edited on Central | **Fail**: the new submission is not ingested | P14 |
| 10 | An unmapped root section is reported as a configuration problem | **Fail**: `ErrorException` (null dereference) | P9 |
| 11 | Two datasets sharing a variable name keep their values apart | **Fail**: the empty dataset's variable returns the other's value | P3 |
| 12 | A guest is refused the submission update route, with no call to Central | **Fail**: the request reaches the service and returns 500 | P2 |

Step 7 confirms the code drops a GeoJSON-shaped value. That Central returns that shape by default comes from its documentation, not from a live server.

### What was not run, and why

The plan asked for a run against a real ODK Central in a container. This machine has no Docker or Podman, and `.env` files cannot be read or written by the tooling, so no real Central could be started or reached. Not covered as a result:

- Everything that depends on Central's real behaviour: P1 (what the app-user token can do), attachment timing (P12), token invalidation (P20), entity-list attachments (P23), Enketo editing end to end (P16).
- Anything needing MySQL: the three failing migrations, the full workbook export (`json_keys`), foreign-key behaviour on delete.
- Draft deploy and publish on a fresh schema (P7 / F9), because the test fixtures insert rows directly and never run those paths.
- Scale (P25) and concurrency (P21).

### Scenario still to run against a real ODK Central

Setup: the reference app on MySQL, a real Central, a queue worker with `--tries=3`, two teams with similar names, one template containing an integer, a text, a `select_multiple` with a list, a `select_multiple … or_other`, a `select_one_from_file` backed by a dataset, a geopoint, an image, a repeat with a nested repeat, and a question whose valid answer is 0. There is no UI for this half, so steps from 5 on are driven by tinker and artisan. "Predicted" is from reading the code.

| # | Step | Correct outcome | Predicted |
| --- | --- | --- | --- |
| 0 | `migrate:fresh --seed` on MySQL | Schema complete | Fails (migrations 016, 018, 027; missing columns) |
| 1 | Create both teams | Two Central projects; app users with form-level access only | App user is project manager (P1). Check `GET /v1/key/<token>/projects/<id>/forms/<form>/submissions` returns 403. |
| 2 | Stop Central part-way through creating a third team | Clean failure, or repairable | Team exists with no project (P22) |
| 3 | Link sections to datasets; deploy drafts | Draft on Central, draft version row | Fails on a fresh schema (P7) |
| 4 | Two draft submissions; fetch drafts | Two draft rows, no entities | Works |
| 5 | Publish both forms | One published version each | Works; `live_needs_update` flips back to true (P18) |
| 6 | Publish again with no changes | No duplicate version | Duplicate rows possible (P18) |
| 7 | Submit from Collect with all fields, 0, nested repeats, photo, geopoint; poll | Full entity tree, 0 kept, geopoint stored, photo attached before the host processor runs | Geopoint missing (P11); processor may run before the photo (P12) |
| 8 | Submit with the `or_other` select answered | Ingested | Job fatals; never retried (P10) |
| 9 | Submit with a large photo on a slow link; poll before the photo arrives | Photo arrives on a later poll | Never fetched (P12) |
| 10 | Run the poll twice at once with 20 new submissions | 20 submissions, 20 root entities | Duplicates or key failures (P8, P21) |
| 11 | Edit a submission in Central; poll | Entities replaced, media not duplicated | Tree doubled (P8); photo attached twice (P5) |
| 12 | Host processor throws once, then succeeds | One entity tree | One extra tree per attempt (P8) |
| 13 | Reject one submission and delete another on Central; poll | Local state reflects both | No change (P15) |
| 14 | Soft-delete a local submission; edit it on Central; poll | Defined behaviour | Pull fails on every poll (P14) |
| 15 | Re-import the template with a renamed question, a removed repeat and a changed list; publish v2; submit one v1 and one v2 | Each projected with its own version's names | v1 loses the renamed value (P9) |
| 16 | Publish a new version directly in Central; submit; poll | Flagged; others unaffected | Nothing after that entry is ingested (P13) |
| 17 | Edit through Enketo as a platform user, then with a non-UTC app timezone; then request the update URL logged out | Authorised users only; returns to the calling page | Lands on `/` (P2); entities wiped with a non-UTC timezone (P16); guest call succeeds or 500s (P2) |
| 18 | Add an entity for team B to a shared lookup dataset; redeploy team A's form; download the CSV from Central | Only A's and shared rows | B's row present (P3) |
| 19 | Deploy a form whose entity list does not exist on Central | Clear error, or the list is created | Published with the attachment missing (P23) |
| 20 | Invalidate the platform session on Central; poll | One re-authentication, then success | Every job fails until the cached token expires, up to 20 hours (P20) |
| 21 | Return 503 during a pull of 10, after 4 are stored | The rest on the next poll, all complete | The fifth keeps missing attachments (P12) |
| 22 | Restart the worker during a pull and during processing | No loss, no duplication | Duplicate tree (P8) |
| 23 | Load 5,000 submissions; poll; poll again with 1 new | Second poll is cheap | Both download everything; timeout or memory exhaustion (P25) |
| 24 | Archive a form just after a device uploads | Final submissions ingested | Never polled again (P19) |
| 25 | Soft-delete team A; poll. Delete a form that has submissions. | Poll skips; delete refused before touching Central | Pull fatals; Central form deleted, then the database delete fails (P17) |
| 26 | Export team A's data | One row per submission, correct headings | Duplicated rows, missing columns (P7, P8, P9) |
| 27 | Unset `ODK_URL`; run the poll | No-op with a message | Connection errors per form (P21) |
| 28 | `odk:get-submissions-quietly`, then a normal poll | No duplication | Everything double-projected (P14) |
