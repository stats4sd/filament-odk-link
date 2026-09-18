# ODK package review

**Date:** 2026-09-08
**Branch:** `dev`
**Commit reviewed:** `e048b4b1276c9db61bbf418823df7824fa93ec9c`
**Reviewer:** Codex, with architecture, bug-review and verification subagents
**Scope:** Package domain models, imports/exports, deployment and submission processing, tenant boundaries, shipped Filament integration, configuration, migrations, tests and CI configuration. This is a review of the current checkout, not a diff or a review of consuming applications.

The package contains useful shared functionality and is worth retaining, but the intended harmonised workflow needs stronger guarantees around owner isolation, preservation of customisations, immutable revisions and reliable processing. These are more urgent than formatting or reducing class count. The [separate architecture/distribution assessment](2026-09-08-package-or-application.md) considers whether to keep a package or build a full application.

**Terminology and scope correction:** This checkout has `XlsformTemplate`, `XlsformModule`, mutable `XlsformModuleVersion`, and deployed `XlsformVersion`. It has no `XlsformTemplateVersion`. It also has no `src/OdkLinkTeam.php` or `src/Filament/OdkTeam/`, despite the repository overview describing them. Only the existing admin integration was inspected. “Optional modules” are therefore treated as an intended product requirement, not an already complete implementation.

**Validation:** `composer test` passed on PHP 8.4.17 with the installed Laravel 13.16.1 / Filament 5.6.7 dependencies. `composer analyse` failed with 134 reported errors; these include typing and baseline issues, not 134 confirmed runtime bugs. Thirteen additional disposable Pest probes passed with 30 assertions, asserting observed defects rather than correct behaviour. They used Testbench/SQLite and fake/mock external services. Temporary tests were removed. No production code was changed and no live ODK server or consumer database was exercised. Performance estimates below are derived from code, not benchmarks.

**Reading the findings:** P1 means prioritise before a stable release because of isolation, data loss, incorrect data or a broken core workflow; P2 means a narrower correctness or compatibility failure. “Reproduced” means exercised by a focused probe; “static” means the trigger and failure follow from inspected code but were not run end to end. Source references are repository-relative paths and line numbers at the reviewed commit.

## 1. Architectural problems

### A1. Separate module variants, immutable revisions, template releases and form selections

**Priority: high.** `XlsformModuleVersion` currently combines a reusable variant and its editable contents. Survey imports upsert into the same version (`src/Imports/XlsformTemplate/GetsModuleNamesPerRow.php:23`; `XlsformTemplateSurveyImport.php:134`). Forms select these mutable records (`src/Models/OdkLink/Xlsform.php:359`). `XlsformVersion` is deployment history, not a release of the upstream template.

This prevents the system from answering reliably: “Which exact approved questions did this team use?”, “What changed upstream?”, and “Will accepting that change overwrite our customisation?” A shared mutable version can change the next export of multiple forms. Neither a `has_latest_template` flag nor a stored XLSX alone provides an owner upgrade policy.

Introduce distinct concepts, incrementally:

- A module describes identity and permitted customisation: required/optional, replaceable/extendable, and compatibility constraints.
- An immutable module revision contains the approved questions, choices and translations. An owner variant records the revision it was based on.
- A template release pins a compatible set of module revisions and their default ordering.
- A form composition pins selected revisions and explicit exclusions/overrides. Upgrades compare the old base, new base and owner changes and require a deliberate resolution policy.
- A build records the resolved composition, source revision IDs, schema, media manifest and content hash used for a deployment.

`syncWithTemplate()` currently adds every missing template module (`src/Models/OdkLink/Xlsform.php:257`). There is no durable distinction between “not yet added” and “owner deliberately excluded this optional module”; a subsequent sync restores a removed selection. Model that decision before presenting optional modules as supported. Decide separately whether a custom variant is shared across an owner's forms or belongs to one form; the current `owner_id` on variants gives owner-level sharing.

Keep existing models as adapters during migration. Immutable snapshots and explicit upgrade operations are sufficient; event sourcing is unnecessary.

### A2. Move workflow orchestration out of persistence hooks

**Priority: high.** Creating a form calls `setup()`, which synchronises and deploys it (`src/Models/OdkLink/Xlsform.php:79`, `:479`). Saving an available template iterates owners and distributes forms (`src/Models/OdkLink/XlsformTemplate.php:67`). Deleting a form calls Central (`Xlsform.php:83`). Adding media launches an import (`src/Listeners/HandleXlsformTemplateAdded.php:24`).

A normal Eloquent operation therefore performs remote work, queues more work, and changes related records. This obscures failure boundaries and makes seeding, transactions, host extensions and testing difficult. Moving the same code to observers would improve file organisation but retain the implicit workflow.

Extract explicit actions such as `ImportTemplate`, `CreateOwnerForm`, `ChangeFormComposition`, `BuildForm`, `DeployDraft` and `PublishForm`. Keep Eloquent for persistence and small local invariants. Record an operation with its input revision/media ID and status; commit local changes before dispatching remote work. Laravel supports after-commit dispatch and queue middleware for overlapping jobs. [Laravel queues](https://laravel.com/framework/docs/13.x/queues)

Do not hold a database transaction open across network calls. A database rollback cannot roll back a successful Central request. Reconcile remote state on retries and separate local build completion from remote deployment completion. Use per-form locks or equivalent concurrency control; checking a `processing` boolean and later setting it is not an atomic lock.

### A3. Treat XLSForm import/export as a lossless compilation pipeline

**Priority: high.** Survey headers, casts, translated fields and output headings are separately hardcoded in the importer, `SurveyRow`, and export classes. Settings are recreated with fixed defaults. The resulting drift already loses zero values, conditional required expressions and trigger behaviour (B11–B13).

Parse a workbook once into a validated representation of survey, choices, settings and extensions. Preserve unknown columns and expressions unless explicitly unsupported; report unsupported features rather than silently discarding them. Compile a selected composition through that representation, validating duplicate question names, group/repeat balance, choice-list references, translation availability and module compatibility.

Import into staging records identified by an import operation, then activate the complete revision. Current `updated_during_import` flags and cleanup over live relationships allow one import to damage another version or template (B3–B4). Staging also makes interrupted/retried imports much easier to reason about. A small set of worksheet value objects/shared field metadata is enough; do not build a generic spreadsheet framework.

### A4. Use the deployed form's mapping when processing submissions

**Priority: high.** `processSubmission()` receives an `XlsformVersion`, but immediately reads `$xlsformVersion->xlsform->xlsformTemplate->rootSection` (`src/Services/OdkLinkServices/OdkSubmissionService.php:321`). Repeat processing also reads current template sections at lines 389 and 504. Root choices come from the current form selection, while repeat choices come from the template at line 437.

The processing model can therefore differ from both the historically deployed form and the owner's selected/customised modules. Changing a template mapping can change how an old submission is interpreted; adding an owner-only field does not automatically give it a template section mapping. Storing a schema column on a version is insufficient if processing ignores that snapshot.

Compile and persist the resolved section/dataset mapping alongside each deployed build. Resolve incoming submissions by remote form version and use that build's mapping and choice definitions. Keep raw submission content as the recoverable source, with projection version/status and deliberate reprocessing when the host changes its mapping. Test old-version submissions after a new template release and owner-only repeat groups.

### A5. Make owner context and authorization explicit

**Priority: high.** `Xlsform`, `Submission` and `ChoiceListEntry` apply ownership scopes only when `HelperService::getCurrentOwner()` finds a Filament tenant (`src/Models/OdkLink/Xlsform.php:88`; `Submission.php:45`; `ChoiceListEntry.php:40`; `src/Services/HelperService.php:86`). CLI, queue and external callback contexts behave differently. `cloneForOwner($owner)` even reads choice entries using the ambient scope rather than solely its argument (`XlsformModuleVersion.php:139`, `:156`).

Separate three questions: which data an operation reads, whether an actor may perform it, and whether a dataset/template is deliberately shared. Pass owner identity into core actions and use reusable SQL scopes such as `visibleToOwner()`. Apply policies at user-facing entry points and validate cross-record associations inside actions. Reserve an explicit privileged context for operations across owners. Filament scopes resource queries, but that does not establish authorization for arbitrary backend queries or package routes. [Filament tenancy](https://filamentphp.com/docs/5.x/users/tenancy)

Test at least two owners across HTTP, queue, console, exports and cloning. The confirmed dataset-export leakage and guest callback in B1–B2 demonstrate why this must be a core boundary, not just panel configuration.

### A6. Separate Central transport, ingestion, projection and notifications

**Priority: medium/high.** `OdkLinkService` uses six traits; `OdkSubmissionService` alone fetches remote data, downloads attachments, persists submissions, recursively creates entities, exports spreadsheets and expands select-multiple values. Traits divide files but still create one large class with shared implicit state. Jobs also embed Filament notification and application role assumptions.

Keep a Central client responsible for authentication, requests and typed/consistent responses. Put ingestion/checkpointing, attachment transfer and entity projection in separate operations. Track each stage independently: saving a remote revision does not mean its attachments or host processing succeeded (B5–B6). Define retry/idempotency at the submission revision and processing-version boundary.

Use a small host `SubmissionProcessor` contract with a default no-op implementation, and events/listeners for notifications. Do not introduce a generic repository for every Eloquent model or an interface for every operation. The current client remains a useful shared gateway; its responsibilities need reducing, not discarding.

### A7. Stabilise the package boundary and host-app contract

**Priority: medium/high.** Configuration advertises replaceable owner/user models, but core jobs assume owner media, particular role names and domain conventions. `FinishChoiceListEntryImport.php:35` special-cases `location`, and line 58 embeds unit-conversion UI configuration. `ImportAllLanguageStrings.php:39` expects host `custom_questions` media. `NotifiesOnJobFailure.php:43` hardcodes `Super Admin`. `OdkLinkAdmin.php:27` discovers all resources rather than exposing an explicit replacement/configuration API.

Keep shared ODK logic independent of Filament and move standard resources/notifications into an integration layer. Define narrow extension points for real differences: owner identity, recipient resolution, import enrichment, submission processing and UI actions/schema. Document supported owner key types; migrations currently use integer `foreignId()` and do not make an arbitrary UUID owner compatible merely by changing the model configuration.

Retain package configuration and conventional Laravel integration; shipping routes/views is supported package design. The useful distinction is between reusable behaviour and application policy. See the [distribution assessment](2026-09-08-package-or-application.md) for a reference app and incremental separation strategy.

## 2. Code gotchas, Laravel practices and unnecessary complexity

### C1. Export eager loading is defeated by fresh relationship queries

`src/Exports/XlsformExport/ExportsXlsformContent.php:36` queries `languageStrings()` for every row, locale and translated field. `XlsformSurveyExport.php:60` already eager-loads language strings, but the method call ignores that loaded collection. Six translated fields × 1,000 survey rows × three locales implies roughly 18,000 translation lookups, before choices or related-model queries.

Load strings and their locale/type data per export chunk and index them by locale/type. Eager-load choice lists used by `type_and_choice_list`. Verify bounded query counts on a representative multilingual export. Also avoid rebuilding the choice collection inside every repeat record (`OdkSubmissionService.php:437`).

### C2. Move filtering and iteration to SQL where appropriate

`XlsformTemplate.php:88` loads all owners before checking eligibility. `XlsformTemplateWorkbookImport.php:48` loads all rows before selecting stale ones. Polling downloads the complete remote metadata and OData submission collection and filters in memory (`OdkSubmissionService.php:115`, `:130`, `:143`). These costs grow with every tenant and submission, even when there is little new work.

Use query predicates and `lazyById()`/`chunkById()` for local processing; retain individual model deletion where its events are required. For remote polling, use supported pagination/incremental retrieval with a durable checkpoint and reconciliation strategy. Verify the target Central API's guarantees before choosing a timestamp cursor. A count accessor that fetches the full remote submission list (`OdkSubmissionService.php:63`) should become an explicit/cached metric, not hidden work during rendering.

### C3. Display names and incomplete contracts are being used as identity

`Xlsform.php:268` and `:306` identify variants through owner plus a generated name. `cloneForOwner()` generates a different name. Use stable module/owner/revision identity and database constraints; leave names editable labels. B10 demonstrates the collision.

Interfaces such as `WithXlsforms` enumerate many Eloquent relationships and assume dynamic model properties; the trait alone does not make a host implement that interface. PHPStan reports invalid trait types, incomplete property contracts and incorrect relationship generics. Tighten the documented model contract and correct return types rather than extending the suppression baseline. Keep genuinely different template-owner polymorphism distinct from the single configured form-owner model.

### C4. Consolidate duplicate algorithms, not different domain concepts

`makeMultiSelectBooleans()` and `makeMultiSelectBooleansFromSurveyRow()` implement parallel paths (`OdkSubmissionService.php:535`, `:562`). Root/repeat processing duplicates choices, value creation and section traversal. Media-header expansion appears in the export trait and is repeated in `XlsformChoicesExport.php:99`. Centralise these small algorithms around the resolved form definition.

Conversely, forcing datasets/entities and static/localised choices into one model just because both export CSV would blur their lifecycles. Share an attachment-building interface where needed, keeping their ownership and validation rules separate. Reduce broad `HelperService` responsibilities rather than adding more unrelated helpers.

### C5. Remove speculative and host-specific infrastructure

`HelperService::getModels()` scans and instantiates classes to infer a model from a table name (`src/Services/HelperService.php:24`, `:98`). A small registry of allowed dataset models is clearer and avoids discovery assumptions. `HasXlsforms` also carries country/language/choice completion responsibilities beyond simple form ownership.

All files in `src/Commands` are automatically registered (`src/FilamentOdkLinkServiceProvider.php:103`), including large development utilities. Register supported commands deliberately and move fixture-generation/debug commands into development tooling. Remove unused host-model imports and scaffold files; their presence falsely suggests supported integration points.

### C6. Follow Laravel conventions without treating every style preference as a framework rule

Use container injection in controllers/actions/jobs, model binding plus authorization for records, meaningful return types, deliberate casts and field allowlists at input boundaries. The current controller bypasses binding with `find()` and does not handle a missing record (`SubmissionController.php:13`). The broad `$guarded = []` pattern is not itself a vulnerability, but requires validated, explicitly constructed input.

The local PHP guide's preferences for early returns, imports, typed properties and descriptive variables are reasonable cleanup targets. Rules such as splitting every `&&`, forbidding all `else`, or omitting migration `down()` methods are local style choices, not universal Laravel correctness rules. A package-specific config file is appropriate despite the guide's general instruction to put service config in `config/services.php`. Do not spend a rewrite budget on these mechanical differences.

### C7. Eliminate dead exception handling and fragile parsing

`src/Filament/OdkAdmin/Resources/XlsformTemplates/Pages/CreateXlsformTemplate.php:139` rethrows before the notification/validation conversion, making the remaining catch block unreachable. Choose the actual error contract and implement it once. Low-level services should throw meaningful exceptions; presentation code should decide how to render them.

`HelperService::importCsvFileToCollection()` manually splits on `PHP_EOL` and uses a regex to remove newlines inside cells (`HelperService.php:64`). It is not a complete CSV parser. Use a stream parser such as `fgetcsv()` and tests for quoted embedded newlines, escaped quotes and line endings. This is a concrete fragility; no CSV failure probe was included in this review.

### C8. Storage and transport policies need one consistent implementation

`prepareCsvFile()` returns a configured-disk path, which upload code treats as a local filesystem path (`src/Services/OdkLinkServices/OdkFormMediaService.php:76`, `:204`). Workbook imports similarly use media `getPath()`. These assumptions make remote disks problematic despite configurable disk names. Either document local/shared-worker storage as a supported requirement or materialise remote objects into unique temporary files and stream transfers.

Central authentication uses a fixed `odk-token` cache key (`src/Services/OdkLinkService.php:28`). With one endpoint/account and separate app cache prefixes this is not an established bug. If multiple configured clients share a cache namespace, key it by endpoint/account and centralise refresh/retry policy. Avoid broad retries for remote writes without checking whether they are safe to repeat.

### C9. The current verification and documentation overstate readiness

Tests provide useful unit coverage, but fixture builders insert directly into tables to bypass lifecycle hooks (`tests/Pest.php:30`), model tests mock a non-tenant panel, and many ingestion tests mock processing. That is sensible isolation, but leaves the actual workflow unverified. The export constructor fails on the suite's SQLite database (B19); migrations passing does not prove deployment can persist its state (B7).

Add a runnable reference consumer and targeted integration coverage for normal creation hooks, two-owner exports, owner customisation surviving template updates, complete queued imports, failed attachments, edited submissions, and fresh-install deployment. Test supported production databases and upgrade migrations as well as SQLite. Do not turn every style change into a new test.

`README.md` remains scaffold text, with an empty configuration example and an unrelated `echoPhrase()` usage sample. Replace it with a working installation and host contract, actual plugin registration, queue/storage requirements, ownership rules and an example lifecycle. Resolve the nonexistent tenant-plugin description. Correct CI's dependency matrix (B21) and triage the 134 PHPStan findings before calling the package stable.

## 3. Actual bugs

### B1. P1 — Dataset attachment export includes other owners' records

**Evidence:** `src/Exports/DatasetAsMediaAttachmentExport.php:35`, `:50`; `src/Models/OdkLink/Dataset.php:89`; `src/Services/OdkLinkServices/OdkFormMediaService.php:218`.

**Trigger/impact:** A shared, non-universal dataset contains entities for owners A and B. Generating A's lookup CSV loads every entity and exports B's record identifiers, labels and values as well. `is_universal` does not control this selection, and `Entity` has no tenant scope to compensate.

**Verification:** Reproduced with two owners and `is_universal=false`; B's entity appeared in A's export collection. This establishes the leak in generated output, without a live upload.

**Fix/check:** Query explicitly for the intended owner and deliberately shared entities. Test both private and universal datasets in a queue context without a selected Filament tenant.

### B2. P1 — A guest can trigger privileged submission synchronization

**Evidence:** `routes/web.php:7`; `src/Http/Controllers/SubmissionController.php:13`; `src/Models/OdkLink/Submission.php:45`.

**Trigger/impact:** An unauthenticated GET to `/odk/submissions/{existing-id}/update` finds the record and invokes `updateSubmission()` with platform Central credentials. No route middleware or policy protects it. It permits unauthorized synchronization and associated mutation/processing; it does not directly return submission contents.

**Verification:** A guest request invoked the mocked service and redirected; `gatherMiddleware()` returned an empty array.

**Fix/check:** Use an authenticated, authorized callback with explicit owner resolution, or a narrowly scoped expiring signed callback if the Enketo round trip cannot retain authentication. Ensure the appropriate session middleware is present for the return URL. Test guest, wrong-owner, expired and valid requests. Prefer an authorized write operation rather than a general mutating GET.

### B3. P1 — Template re-import deletes owner questions and can rewrite owner-variant choices

**Evidence:** `src/Models/OdkLink/XlsformTemplate.php:312`, `:322`; `src/Imports/XlsformTemplate/GetsModuleNamesPerRow.php:23`; `XlsformTemplateWorkbookImport.php:48`; `XlsformTemplateChoicesImport.php:31`, `:68`.

**Trigger/impact:** An owner has a custom module version under a template module. Template survey import updates defaults, but cleanup traverses all versions and deletes the owner's previously completed rows because their import flag is false. Choice import also searches all versions' same-named lists and upserts template properties into those lists, potentially overwriting matching cloned entries.

**Verification:** Owner survey-row deletion reproduced by invoking the real cleanup method. The choice overwrite path is static evidence; its exact collision depends on the choice key values.

**Fix/check:** Restrict every import read, write, reset and cleanup to the revisions owned by that import. Test re-import with owner questions, cloned choice lists and owner-specific entries already present.

### B4. P1 — Importing one workbook deletes unrelated empty choice lists

**Evidence:** `src/Imports/XlsformTemplate/XlsformTemplateWorkbookImport.php:67`.

**Trigger/impact:** Any workbook cleanup executes `ChoiceList::has('choiceListEntries', '=', 0)->delete()` globally. An empty list being configured under template B disappears when template A is imported.

**Verification:** Reproduced with two unrelated templates.

**Fix/check:** Scope cleanup to the current import's lists, and distinguish obsolete lists from intentionally empty or in-progress lists. Assert unrelated templates remain unchanged.

### B5. P1 — Edited submissions are projected twice

**Evidence:** `src/Services/OdkLinkServices/OdkSubmissionService.php:183`, `:196`, `:340`; `src/Models/OdkLink/Submission.php:62`; `src/Jobs/OdkSubmissions/ProcessOdkSubmission.php:28`.

**Trigger/impact:** Polling a remote edit updates `content`. The model observer deletes and recreates derived entities synchronously, then the polling service queues another processor. That processor unconditionally creates another entity tree. Counts and downstream reports can consequently contain duplicates.

**Verification:** A probe counted two calls to `processSubmission()`: one during persistence, one from the queued job. Entity duplication follows from the inspected unconditional `Entity::create()` implementation; the probe did not run the full real projection twice.

**Fix/check:** Have one revision-aware processing path with transactional replacement or idempotent writes. Test edited submissions and retry after a partial projection.

### B6. P1 — Failed attachment downloads are not retried by normal polling

**Evidence:** `src/Services/OdkLinkServices/OdkSubmissionService.php:115`, `:138`, `:183`, `:198`.

**Trigger/impact:** Persistence records the latest remote revision, then an attachment request fails. The next poll regards that revision as already ingested and excludes it. Missing media remains missing until another remote edit or a separate repair process.

**Verification:** First poll threw from a mocked attachment transfer; second poll returned zero and did not retry it.

**Fix/check:** Persist attachment-transfer state and retry independently from revision ingestion. Test a failed first transfer followed by a successful retry with unchanged remote metadata.

### B7. P1 — Fresh package migrations lack the draft-version column used by deployment

**Evidence:** `database/migrations/009_create_xlsform_versions_table.php:14`; `src/Jobs/XlsformDeployment/DeployDraftXlsformToOdkCentral.php:84`; `src/Services/OdkLinkServices/OdkFormService.php:303`.

**Trigger/impact:** Draft and published version writes use `xlsform_versions.is_draft`, but no shipped migration defines it. A fresh installation fails when persisting that version. Remote deployment and the preceding local state save may already have succeeded.

**Verification:** Column absence and the exact deployment-style `updateOrCreate(['is_draft' => true], ...)` failure reproduced on migrated Testbench tables.

**Fix/check:** Add an upgrade migration and deliberate backfill for existing records. Test installation followed by draft and publication persistence; do not merely assert that tables exist.

### B8. P2 — Draft deployment flips the live-update flag and can emit a false publication event

**Evidence:** `src/Jobs/XlsformDeployment/DeployDraftXlsformToOdkCentral.php:77`; `src/Jobs/XlsformDeployment/PublishXlsformOnOdkCentral.php:42`; `src/Models/OdkLink/Xlsform.php:71`.

**Trigger/impact:** Deploying a draft while `live_needs_update=true` flips it to false, causing the saved hook to emit `XlsformWasPublished` without publishing. Conversely, publication clears the flag and then recreates the draft, which flips it back to true.

**Verification:** Static control-flow proof; this is reachable once the schema supports deployment.

**Fix/check:** Assign state from explicit draft/published build identities; do not toggle. Test repeated draft deployments and post-publication draft recreation.

### B9. P2 — A remote edit to a soft-deleted submission breaks polling

**Evidence:** `src/Services/OdkLinkServices/OdkSubmissionService.php:115`, `:183`; `database/migrations/010_create_submissions_table.php:16`.

**Trigger/impact:** Detection includes trashed submissions, but persistence uses a relationship that excludes them. After a remote edit, `updateOrCreate()` attempts a new row with the existing unique `odk_id`, aborting the polling pass.

**Verification:** Reproduced `UniqueConstraintViolationException` after soft-deleting a stored submission and returning a new remote revision.

**Fix/check:** Decide whether local deletion remains authoritative or an edit restores the record; implement the same rule in detection and persistence. Test both unchanged and edited trashed submissions.

### B10. P1 — Owner module identity collides across templates and cloning does not match replacement lookup

**Evidence:** `src/Models/OdkLink/Xlsform.php:268`, `:306`; `src/Models/OdkLink/XlsformModuleVersion.php:145`.

**Trigger/impact:** Two templates contain extendable modules with the same name. For the same owner, both forms reuse a local version found by owner and generated name, with `xlsform_module_id=null`. Editing one local module consequently changes both forms' shared customisation. Separately, `cloneForOwner()` uses `"<module> - <owner>"`, while automatic replacement searches for `"Local <module>"`, so the clone is not selected automatically.

**Verification:** Same local record attached to two distinct templates, with null module linkage, reproduced. The nullable schema permits insertion; this is not a foreign-key exception. The clone-name mismatch is static.

**Fix/check:** Use module identity and explicit variant ownership; define whether customisations belong to an owner or a particular form. Test same-named modules in distinct templates and clone → replace behaviour.

### B11. P2 — Survey import discards valid zero values

**Evidence:** `src/Imports/XlsformTemplate/XlsformTemplateSurveyImport.php:39`, `:47`, `:101`.

**Trigger/impact:** Bare collection `filter()` removes numeric zero and string `"0"`. Defaults, calculations, repeat counts and extension properties therefore change or disappear during import.

**Verification:** A probe supplied `default='0'`, `calculation='0'`, `repeat_count=0` and a zero extension property; they became null/absent.

**Fix/check:** Filter only genuinely empty cells using explicit comparisons and preserve value types through export. Test a complete zero-valued round trip.

### B12. P2 — Conditional required expressions become false

**Evidence:** `src/Imports/XlsformTemplate/XlsformTemplateSurveyImport.php:84`; `src/Models/OdkLink/SurveyRow.php:37`.

**Trigger/impact:** A valid condition such as `${age} > 18` in `required` is mapped to zero and cast to false. The generated question no longer requires an answer under that condition. XLSForm supports expressions for conditional requirements. [ODK form logic](https://docs.getodk.org/form-logic/)

**Verification:** The expression above reproduced a boolean false value.

**Fix/check:** Preserve expressions as strings and normalise only recognized literal forms. Update storage/casts and test literal and conditional required fields through generation.

### B13. P2 — Regeneration drops trigger behaviour and replaces template settings

**Evidence:** `src/Imports/XlsformTemplate/XlsformTemplateSurveyImport.php:103`, `:130`; `src/Exports/XlsformExport/XlsformSurveyExport.php:70`, `:93`; `src/Exports/XlsformExport/XlsformSettingsExport.php:28`.

**Trigger/impact:** `trigger` is recognised and stored on import, so it is excluded from generic properties, but no trigger column is mapped or headed on export. Regenerated calculations lose their trigger configuration. The settings exporter also substitutes a fixed `instance_name` of `"instance"` instead of preserving the template's expression.

**Verification:** Static import/export mapping comparison. No live Central conversion was run.

**Fix/check:** Preserve supported settings and survey fields in the shared compilation representation, overriding only the fields deliberately controlled by the platform. Include a trigger and a custom instance-name expression in a round-trip fixture.

### B14. P2 — Adding ordinary template media launches a workbook import

**Evidence:** `src/Listeners/HandleXlsformTemplateAdded.php:30`; `src/Models/OdkLink/XlsformTemplate.php:138`.

**Trigger/impact:** The listener checks model type but not media collection. Adding an image/audio/CSV in a template's `attached_media` collection enters module/workbook parsing, potentially throwing or starting unintended import work.

**Verification:** Static event/listener path.

**Fix/check:** Require the expected XLSForm upload collection and supported file type before parsing. Test that an ordinary attachment does not dispatch an import.

### B15. P2 — Choice-list prerequisites and workbook import are queued independently

**Evidence:** `src/Listeners/HandleXlsformTemplateAdded.php:70`, `:75`; `src/Imports/XlsformTemplate/XlsformTemplateSurveyImport.php:61`.

**Trigger/impact:** With multiple workers, the workbook's survey import can execute before the separately queued choice-list import completes. Select questions dereference a missing list; choices processing may also see no matching list. FIFO dispatch does not establish completion order across workers or chunks.

**Verification:** Static dependency/race analysis; no concurrent-worker reproduction was run.

**Fix/check:** Chain completion of the prerequisite import before the dependent workbook import, including its chunk jobs. Test dispatch structure and an intentionally delayed prerequisite.

### B16. P2 — Owner module translations read the wrong file

**Evidence:** `src/Jobs/ImportAllLanguageStrings.php:39`.

**Trigger/impact:** For an owner module, the job discards its supplied upload path and calls `$owner->getFirstMediaPath('custom_questions')`. A valid configured owner need not implement media-library methods. Even if it does, that collection can be absent or hold a different file. Translation import fails or reads unrelated content.

**Verification:** Static path/contract comparison; actual module uploads belong to the module version.

**Fix/check:** Use the input media ID/path for the import operation. Test an owner without media support and a module whose file differs from any owner attachment.

### B17. P2 — A localisable standalone module import calls a nonexistent relationship

**Evidence:** `src/Jobs/FinishChoiceListEntryImport.php:21`, `:85`; `src/Models/OdkLink/XlsformModuleVersion.php`.

**Trigger/impact:** The job explicitly accepts a module version, but when a choice list qualifies as localisable it calls `requiredDataMedia()`, which that model does not implement. It fails after modifying choice-list state.

**Verification:** Static accepted-type/method comparison. The trigger requires a list matching the job's localisable query, not every module upload.

**Fix/check:** Resolve required media through the appropriate template/form context or split template-specific finalisation from module import. Test that qualifying branch for both supported model types.

### B18. P2 — Choices hidden by an owner remain in generated output

**Evidence:** `src/Models/OdkLink/ChoiceListEntry.php:110`; `src/Models/OdkLink/ChoiceList.php:36`; `src/Exports/XlsformExport/XlsformChoicesExport.php:43`; `src/Exports/ChoiceListAsMediaAttachmentExport.php:31`.

**Trigger/impact:** Hiding a global choice records the owner in `choice_list_entries_removed_owner`, but export selection checks ownership without excluding that pivot. The choice still appears in the generated choices sheet and CSV attachment.

**Verification:** Reproduced that `isRemoved($owner)` is true while `getOwnedEntries($owner)` returns the entry. CSV consumption of that collection and the XLS query omission are static evidence.

**Fix/check:** Centralise owner-visible choice selection including exclusions and use it in all exporters/processors. Invalidate affected builds when the selection changes.

### B19. P2 — Workbook generation fails on SQLite

**Evidence:** `src/Exports/XlsformExport/XlsformSurveyExport.php:254`; `XlsformChoicesExport.php:145`; `src/Exports/ChoiceListAsMediaAttachmentExport.php:62`.

**Trigger/impact:** Export construction invokes MySQL-style `json_keys()`. The package's SQLite test environment has no such function, so a complete generated workbook fails even though the present suite passes. This is a portability failure, not a claim that the same expression fails on MySQL.

**Verification:** Constructing `XlsformSurveyExport` on migrated Testbench tables reproduced `no such function: json_keys`.

**Fix/check:** Collect property keys portably in PHP or implement and test database-specific queries. Alternatively, explicitly restrict database support and add tests on that database; avoid claiming SQLite end-to-end support from the present suite.

### B20. P2 — Storage defaults read the wrong Laravel configuration key

**Evidence:** `config/filament-odk-link.php:55` (the `storage` entries).

**Trigger/impact:** Both defaults read `config('filesystem.default', 'local')`, singular. Laravel's key is `filesystems.default`. A host that selects a different default disk but does not override package storage silently gets `local` instead.

**Verification:** Static literal/key comparison against the installed application's filesystem configuration convention.

**Fix/check:** Correct the key and verify the package defaults under a non-local default disk. This does not by itself fix the local-path assumptions described in C8.

### B21. P1 — CI dependency matrices cannot install the declared package requirements

**Evidence:** `.github/workflows/run-tests.yml:16`; `.github/workflows/phpstan.yml:27`; `composer.json:23`.

**Trigger/impact:** Tests select PHP 8.1/8.2, Laravel 10 and Testbench 8; PHPStan selects PHP 8.1. The package requires PHP ^8.4 and Illuminate ^13, with Testbench ^11 in development dependencies. These jobs cannot resolve/install the advertised package stack when triggered. They also target `main`, so they do not validate every change made directly to `dev`.

**Verification:** Static comparison of local workflow files with Composer constraints; no remote CI history was queried or workflow triggered.

**Fix/check:** Replace the matrix with supported PHP/Laravel/Testbench versions and intentional lowest/stable dependency jobs. Run the actual package tests and static analysis against those installations.

### Remediation order and verification gates

1. Fix B1–B4 first: isolate data access, authorize callbacks and stop destructive imports. Preserve representative owner customisations as regression fixtures.
2. Fix B5–B10 and the CI matrix: establish reliable deployment/submission state and stable module identity before expanding functionality.
3. Fix round-trip and import/export correctness (B11–B20), then introduce immutable revisions and explicit composition through small migrations/actions.
4. Verify a complete two-owner workflow in a reference app: import template, customise one owner, publish both, revise the template, opt one owner into an upgrade, and ingest both old and new submissions. Check failed/retried jobs and media transfer as part of that workflow.

The larger redesigns in section 1 should follow these regression cases. Reorganising files or moving to application forks before protecting the data would make it harder to tell whether the rewrite preserved the intended behaviour.
