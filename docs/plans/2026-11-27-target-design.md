# Target design for the ODK Link packages

**Status**: Not Started

A proposal for review, not an accepted plan. Nothing here is built. It is the output of part B of [2026-11-27-package-review-and-redesign.md](2026-11-27-package-review-and-redesign.md), derived from the findings in [docs/code-reviews/2026-11-27-package-structure-review.md](../code-reviews/2026-11-27-package-structure-review.md) and constrained by the [requirements ledger](2026-11-27-hotfix-requirements-ledger.md). Section 9 lists the decisions needed before any C item is planned in detail; several change the schema.

## 1. Principles

- One row per real-world thing, with a natural key that contains no NULLs.
- No content flags (`updated_during_import`, `processing`, `draft_needs_update`, …) and no orchestration in model events. Every write goes through an action that knows its owner.
- A form selects **modules**; its content is resolved at build time from the module's base content plus the owner's variations.
- A build is an immutable record. Submissions are interpreted against the build that produced them.
- The core never reads an ambient "current owner".
- Prefer the simplest design that removes a cause. Where a larger option exists it is named and deferred.

## 2. Schema

Convention: every owner-scoped table carries `owner_id` (nullable foreign key, cascade on delete) and `owner_key` (unsigned integer, NOT NULL, `0` = global, otherwise equal to `owner_id`). Unique keys use `owner_key`, which removes the NULL-in-unique-index problem on MySQL and SQLite alike.

| Table | Key columns | Unique | Scope |
| --- | --- | --- | --- |
| `xlsform_templates` | title, owner morph, ODK draft columns, `settings` json (the imported settings sheet, including `instance_name`), `default_language_id`, operation columns (section 5) | | global |
| `template_imports` | template, media, checksum, status, errors json, counts json, timestamps, created by | | global; doubles as the template's revision history |
| `xlsform_modules` | template, `key` (slug of the module column), label, position, `customisation` (`none` / `extend` / `replace`), `is_optional`, `content_hash`, `retired_at` | `(template_id, key)` | global |
| `module_variants` (was `xlsform_module_versions`) | `xlsform_module_id` NOT NULL, owner, `kind` (`base` / `extension` / `replacement`), `source` (`import` / `upload` / `host` / `clone`), `base_content_hash` | `(xlsform_module_id, owner_key, kind)` | base is global; others belong to an owner |
| `survey_rows` | variant, name, type, position, the XLSForm columns as text (including `required` and `trigger`), `extra` json keyed by the original header, choice list, path | `(variant_id, name)` | follows the variant |
| `choice_lists` | template, owner, `list_name`, `is_localisable`, `can_hide_entries`, `extra_fields` json | `(template_id, owner_key, list_name)` | global; owner-level only for a list that exists solely in an owner's variant |
| `choice_list_entries` | list, owner, name, `filter_key` NOT NULL default `''`, position, extra json | `(choice_list_id, owner_key, name, filter_key)` | global, or added by an owner |
| `choice_entry_exclusions` | owner, entry | pair | owner |
| `translations` (was `language_strings`) | item morph (survey row or choice entry, through a morph map), `field` (label, hint, guidance hint, the three messages, image, audio, video), locale, `owner_key`, text, `source_hash` | `(item, field, locale_id, owner_key)` | global; `owner_key > 0` is that owner's override |
| `languages`, `locales`, `owner_languages` | as today, with unique keys and operation columns on `locales` | | |
| `xlsforms` | template, owner, title, ODK ids, `draft_build_id`, `published_build_id`, `stale_since`, operation columns | | owner |
| `form_modules` (was `selected_xlsform_module_versions`) | form, module, `included`, position | pair | owner |
| `form_builds` (was `xlsform_versions`) | form, number, `input_hash`, template import, `manifest` json, `schema` json, `mapping` json (repeat path → dataset), ODK version string, status, error, requested by; the xlsx as media | `(xlsform_id, number)` | owner; immutable once built |
| `submissions` | form, **`form_build_id`**, `odk_id`, revision id, review state, attachments expected and present, `projected_revision`, `media_synced_revision`, `host_processed_revision`, content | `(xlsform_id, odk_id)` including trashed | owner |
| `required_media` | template, name, type (nullable until known), dataset, choice list | `(template_id, name)` | global |

Dropped: `language_string_types`, `xlsform_module_version_locale`, `locale_owner`, every `updated_during_import`, every `processing`, `has_latest_template`, `has_latest_media`, `draft_needs_update`, `live_needs_update`, `locales.processing_count`.

Delivered as one fresh migration set (the client database is rebuilt). Datasets, entities and their values keep their shape, with the missing columns and keys added (review P7).

How the main concerns map:

| Concern | In this schema |
| --- | --- |
| The same list used in several modules | One `choice_lists` row. Survey rows in any variant point at it. |
| Entries a team adds | Rows in that one list with the team's `owner_key`, ordered after global entries. |
| Entries a team removes from its context | `choice_entry_exclusions`. Survives re-import because global entry ids are now stable. |
| A team extends a module | An `extension` variant: its rows are appended after the base rows. |
| A team replaces a module (including host-built "Local locations") | A `replacement` variant: used instead of the base. |
| A team rewords a question | A `translations` row with the team's `owner_key` against the base row. No clone. |
| A template re-import | Base variant content is updated in place with stable ids. Extensions and text overrides follow automatically. A replacement stays as it is and records the base hash it was made from, so the UI can show "the base changed since you replaced it". |
| "Which version did this submission come from" | `submissions.form_build_id`; projection reads that build's `schema` and `mapping`. |

### The two largest choices

**Choice lists**

| Option | For | Against |
| --- | --- | --- |
| A. One list per template, owner rows by `owner_key` (**recommended**) | Removes all six name-matching code paths and the app's sibling service. Sweep scope is one template. Export needs no grouping. | Two modules in one template cannot hold different lists with the same name. XLSForm forbids that anyway. |
| B. Keep a list per variant, add a link to a canonical one | Smaller change | Keeps N copies and a sync step: today's defect with a pointer added. |
| C. A platform-wide list library shared across templates | "Countries" maintained once | One template's import sweeps entries another uses; needs versioning to be safe. |

If cross-template sharing is wanted later, add it on top of A. Do not start with C.

**Module variation**

| Option | For | Against |
| --- | --- | --- |
| A. Extension and replacement variants, one of each per owner per module, plus text overrides (**recommended**) | Small. Extensions and overrides follow re-imports with no merge logic. A replacement is an explicit, visible fork. | A team that wants to hide one base question must replace the whole module and then maintain it. |
| B. Row-level overlay (hide, add, reorder, edit against base rows) | Everything follows upstream | Resolution and conflict rules are a project in themselves, and hard to explain to users. |
| C. Full clone per team (today's `cloneForOwner`) | Simplest to write | Never follows the template. This is what the hotfixes were fighting. |

## 3. What a host calls

Invokable action classes in the core. Every owner-scoped action takes the owner, or a model that has one.

| Action | Purpose |
| --- | --- |
| `InspectTemplateWorkbook(file)` | Pure check, no ODK call. Returns structured problems. |
| `ImportTemplate(template, file, by)` | Claims the template, queues one import job. Returns a `TemplateImport` whose state the UI can poll. The same action serves first upload and replace. |
| `DistributeTemplate(template)` / `WithdrawTemplate(template)` | Explicit replacements for the "available" checkbox and its `saved` hook. |
| `CreateForm(template, owner)` | Creates the form and seeds its modules. Does not deploy. |
| `SyncFormWithTemplate(form)` | Adds new modules (included unless optional), in template order after their predecessor. Never changes an existing selection. |
| `SetFormModules(form, selection)` | Include, exclude and order modules. |
| `UpsertOwnerVariant(module, owner, kind, definition)` | For host-built modules. Replaces all rows of that one variant. |
| `ImportOwnerVariantWorkbook(module, owner, kind, file)` | The same parser, scoped to one variant. |
| `CloneBaseAsReplacement(module, owner)` | Idempotent. |
| `AddOwnerChoice`, `UpdateOwnerChoice`, `DeleteOwnerChoice`, `SetChoiceExclusion` | Name validity and uniqueness live here, not in the UI. |
| `SetTextOverride(item, field, locale, owner, text)` | A team's wording for a question or choice. |
| `ExportTranslations(template, locale, owner)` / `InspectTranslationUpload(file)` / `ImportTranslations(…)` | Translation round trip in the core. Rows are identified by natural key (module key, row name, field; list name, entry name, filter, field), never by database id. |
| `ResolveFormDefinition(form)` | Pure read. The single place that applies variants, exclusions and label rules. Used by build, preview and validation. |
| `DeployDraft(form, by)`, `PublishForm(form, by)`, `DeployOwnerDrafts(owner, by)` | Throw `FormBusy` or `OwnerInactive`. |
| `FormState::of(form)` | Lifecycle state, current operation, whether draft and live are stale, last error. No I/O. |
| `EnsureOdkProject(owner)` | Idempotent provisioning, with a repair path. |
| `PollForm(form)` and the ingestion stages (section 7) | |
| `…::deletionImpact()` on template, module, variant, dataset | Counts of forms, submissions and variants affected, for confirmations and refusals. |

Events, dispatched after commit: `TemplateImportCompleted`, `TemplateImportFailed`, `OwnerVariantChanged`, `TranslationsImported`, `FormDraftDeployed`, `FormPublished`, `FormOperationFailed`, `SubmissionIngested`, `SubmissionRevised`, `SubmissionRemoved`.

Authorisation: one `OdkLinkAuthorizer` contract with named abilities (`templates.manage`, `templates.publish`, `choiceLists.manage`, `ownerEntries.manage`, `forms.deploy`, `forms.publish`, `translations.manage`, `submissions.view`). The UI checks it on every action, custom ones included.

Host extension points: `UpsertOwnerVariant`; a listener on `TemplateImportCompleted` for hosts that rebuild their variants; a `ChoiceListCsvProvider` contract; the existing `SubmissionProcessor`, with stated guarantees (after media and projection, once per revision). The `location` and `conversion_rate` special cases move to the host.

## 4. Template import

One job, five stages:

1. **Claim** the template. A second upload while one runs is refused with a message.
2. **Parse** the workbook once, with original headers, into an in-memory structure.
3. **Validate** and collect every error before writing anything: unknown languages, duplicate question names, unbalanced groups and repeats, selects referring to a missing list, duplicate choices. Errors are stored on the `template_imports` row.
4. **Apply** in one transaction: upsert modules by key, base variants, canonical lists, global entries, survey rows by `(variant, name)`, default-language translations, settings, entity lists, required media.
5. **Sweep** in the same transaction, by key set, not by flag: delete what belongs to **this template's base variants and global rows** and was not in the file. Owner rows are never in a sweep predicate. Modules absent from the file are retired, and deleted only when nothing references them. Then mark the template's forms stale, release the claim and dispatch `TemplateImportCompleted`.

Importing the same file twice changes nothing and keeps every id. A crash rolls back, and the claim expires.

## 5. Build, deploy, publish, locking

- **Lifecycle** is derived from `draft_build_id`, `published_build_id` and ODK's active flag: not deployed, draft, live, closed. `stale_since` records that inputs changed after the last build.
- **Claim**: `UPDATE … SET operation = ?, operation_token = ?, operation_started_at = now() WHERE id = ? AND (operation IS NULL OR operation_started_at < ?)`. One affected row means claimed. Visible to the UI, expires on a timeout, needs no cache driver. The same three columns serve forms (deploy, publish), templates (import) and locales (translation import).
- **One job per operation**, not a chain. Deploy: resolve, validate (missing labels, duplicate names), write the xlsx, compute `input_hash`; if unchanged, skip the upload; otherwise upload, read the schema, record the build, clear `stale_since`, release.
- **Publish**: make sure the draft build is current, publish on Central, mark the build published, re-create the Central draft from the same file. No database transaction spans an HTTP call; each step is safe to retry because the build row records how far it got.
- "Live needs update" is computed (`published build hash ≠ draft build hash`), never toggled.

## 6. Labels and owner context

For an item, a field, a form's owner and an output locale:

1. The owner's override in that locale.
2. The global text in that locale.
3. If the locale is not its language's default: steps 1 and 2 for the default locale of the same language.
4. Otherwise empty. For a label this is a build validation error that lists the items, shown before anything is sent to ODK Central.

Never another language (ledger L40). A translation is out of date when its `source_hash` differs from the current default-language text: per string, with no import-time bookkeeping.

Owner context:

- No global scopes in the core. The Filament package scopes its own queries.
- Jobs carry the owner id.
- `FormOwner` gains `isActiveOwner()` (default: not trashed). Reads load trashed owners, so nothing null-derefs. Actions that change or deploy throw `OwnerInactive`; scheduled work skips inactive owners.

## 7. After publish

### ODK Central client

One `request()` that sets the token, timeouts, retries once on 401 after forgetting the token, retries GETs on 429, 5xx and connection errors with backoff, never auto-retries POSTs, and throws one exception type. Token cache key includes the endpoint and account.

Recommended order: add the single `request()` to the existing service first, as a bug fix (about a day). Split the trait-built service into plain classes (`Projects`, `Forms`, `FormMedia`, `Submissions`, `Datasets`) when ingestion is rebuilt, since that empties the largest trait anyway.

### Ingestion

Per-form, unique, staged jobs. Each stage records what it has done, so each can be re-run.

| Stage | Key and state | Notes |
| --- | --- | --- |
| Discover (`PollForm`, unique per form) | `last_polled_at`, `last_poll_error` | Lists ids and system fields only, paged. Compares revision, review state and attachment counts with an id-only local query. No content loaded. |
| Store | Unique `(form, odk_id)` including trashed | Fetches one submission (with `$wkt=true`). An unknown form version is recorded on the row and surfaced once per form; it never stops the batch. |
| Media | Attachment name per submission; `media_synced_revision` | Skips attachments Central does not yet have. Re-runs while present < expected. Stores under a per-submission path. |
| Project | `projected_revision` | In one transaction: delete the submission's entities, rebuild from stored content using the build's schema and mapping. The `Submission::updating` hook goes. |
| Host | `host_processed_revision` | After media and projection, once per revision. Tells the processor whether this is new, revised or removed. |

- Deletes and review state: a periodic full-id reconcile. Policy is decision D12.
- Smaller alternative: patch the current loop (delete entities first inside a transaction, drop the hook, media before dispatch, try/catch per entry, a unique lock). A few days; removes P8, P13 and part of P12. It cannot handle late attachments, deletes, review state or the unbounded re-download, because there is nowhere to record per-stage state. Not recommended as the end state, but it is the fallback if time runs out.

### The rest

- Provisioning: out of the owner `created` hook into `EnsureOdkProject`. App users get form-level access at publish, not project manager (review P1).
- Polling: unique per-form jobs with tries, backoff and timeout; a documented or config-registered schedule; skip when `ODK_URL` is empty; a final sweep for archived forms; one failure notification per form per cause.
- Entity lists: nothing is wired today. Either add one explicit `SyncDatasetToCentral(dataset, owner)` run before deploy, owner-filtered, or delete `OdkDatasetService` and make a missing list a deploy failure with a clear message. Decision D13.
- Commands: an explicit list. Delete `odk:trs`, `odk:test-csv-media-generation`, `odk:get-submissions-quietly`; move `app:generate-submissions` to dev tooling.

## 8. UI delivery

Proposed, replacing the current step 7 shape. This reverses a recorded decision and is decision D1.

- **Admin UI stays runtime library code.** `OdkLinkAdmin` gains the options a host has actually needed: template owner resolver, navigation group, `only` / `except`, a resource replacement map, and the authoriser. Fixes arrive by `composer update`.
- **Opt-in thin stubs**: a publish command that writes a class extending the package class (about ten lines), for a host that does need to change a resource.
- **Team-facing UI ships as runtime components and actions**, not pages: owner choice entries table, owner locales table with translation download and upload, draft-forms panel with a deploy action, module picker, module text editor, owner-forms relation manager, submissions tables. The host composes them into its own pages.
- Each component takes the owner explicitly, and has an "other tenant gets 403/404" test in the reference app.
- The admin UI is rewritten where it is shaped by the old model: one replace action, a choice list resource on canonical lists, variants shown by kind inside the template, read-only dataset relation tabs, impact confirmations on every delete.

Step 7 would become three parts: **7a** admin runtime clean-up (plugin options, authoriser, translation layer, single replace action); **7b** team components for choice entries, draft testing, translations and owner forms; **7c** module editing, picker and submissions, after the corresponding core work.

## 9. Decisions needed

Grouped; the first group changes the schema or the shape of step 7, so it blocks planning.

### Blocking

| ID | Decision | Recommendation |
| --- | --- | --- |
| D1 | Admin UI delivery: host-owned stubs with the plugin deleted (as recorded), or plugin with options plus opt-in thin stubs? | Plugin with options. Groundswell changed no admin resource file. |
| D2 | Are team-facing components in scope for step 7? | Yes, as 7b and 7c. They are where the one real host spent its effort. |
| D3 | Is a team's variation of a module per team (shared by that team's forms from the template) or per form? | Per team. |
| D4 | Do teams ever change a module's structure (add, remove, reorder questions), or only its wording? | If only wording: text overrides replace clones entirely, and `replacement` is needed only for host-built modules. |
| D5 | When the base module changes under a team's replacement: flag only, block publishing until acknowledged, or offer "discard my replacement"? | Flag, with a discard action. |
| D6 | Should a choice list with the same name in two templates be the same list? | Independent for now (option A). |
| D7 | May a team edit or relabel a global choice, or only add its own and hide global ones? What happens when a team-added choice later appears in the template? | Add, hide and relabel (as a text override). Report the clash; do not merge silently. |
| D8 | Is SQLite a supported production database, or test-only? | Test-only; MySQL in CI as well. |
| D9 | Do any other hosts need to upgrade a live database in place? | If none, one fresh migration set. |

### Form-definition behaviour

| ID | Decision | Recommendation |
| --- | --- | --- |
| D10 | A team locale is missing a label and the default locale of that language has none either: build with a blank, or refuse to build? | Refuse, listing the items. |
| D11 | A template contains a language the platform does not know: reject, or create the language? | Reject with a clear message (configurable). |
| D14 | After a template re-import: rebuild team drafts automatically, or mark them stale until someone deploys? Are live forms ever republished automatically? | Mark stale. Never republish live forms automatically. |
| D15 | Optional modules: off by default for existing forms when a new one appears? Can a team exclude a non-optional module? | Off by default; no. |
| D16 | May a module be both extendable and replaceable for the same team? | No: one `customisation` value per module. |
| D17 | Is "one Platform row owns all shared templates" permanent, or will team-owned or programme-owned templates return? | — |
| D18 | Is distributing a template to all owners reversible? What happens to team forms on withdraw, and on template delete? | Withdraw stops new forms; delete is refused while forms have submissions. |
| D19 | One translation locale per language per team, or several with a choice per form? | One. |

### After publish

| ID | Decision | Recommendation |
| --- | --- | --- |
| D12 | A submission deleted or rejected on Central: remove or hide the platform copy and its entities? Does approval gate projection or export? | Mirror deletion as a soft delete; store review state and let the host filter. |
| D13 | Entity lists: is Central the source of truth, or the platform? | Decides whether `OdkDatasetService` is wired or deleted. |
| D20 | Who edits submissions: Central web users only, or platform users without Central accounts? | If nobody needs it, delete the Enketo return route. |
| D21 | One shared app user per owner, or per form or per enumerator with revocation? | At minimum form-level access instead of project manager. |
| D22 | When a question is renamed or removed in a new version, do old answers keep the old variable name? Is re-projecting history required? | Keep under the build's names; re-projection as an explicit action. |
| D23 | Archiving or deleting an owner: close its Central forms and project? What happens to its submissions? | — |
| D24 | Scale to design for: submissions per form, attachment sizes. | Decides per-submission fetch versus bulk paging from the start. |
| D25 | Does any host rely on the per-choice boolean rows for `select_multiple`, or can they be derived at export? | Derive at export. |

### UI and operations

| ID | Decision | Recommendation |
| --- | --- | --- |
| D26 | Who uses the admin panel: Stats4SD staff only, or client admins? | Sets how much guarding, confirmation and translation the admin side needs. |
| D27 | Authorisation: default-deny abilities the host must grant, or permissive with optional policies? | Default-deny. Breaking, but so is everything else in this release. |
| D28 | Should a platform admin see and edit a team's private choices and variants from the admin panel? Today this happens by accident. | Visible, labelled with the owner, read-only by default. |
| D29 | Translation mechanism for the package UI: Laravel key files (step 7 plan) or gettext, which Groundswell uses? | Key files in the package; confirm Groundswell's tooling can consume them. |
| D30 | Is broadcasting a supported host requirement, or must all status work by polling and database notifications? | Polling must always work; broadcasting optional. |
| D31 | Is the admin Dataset UI staying, or replaced by entity lists and choice lists? | If it is going, do not polish it. |

## 10. Effect on the plan's C list

| Plan item | Change |
| --- | --- |
| C1, C2, C9 | Become one piece of work: the new schema, models and `ResolveFormDefinition`. They cannot land separately because the canonical list, variant kinds and fresh migrations depend on each other. |
| C3 | As planned: the five-stage import. Also absorbs the settings sheet (ledger L27–L29) and the round-trip losses (F11). |
| C4 | As planned, plus `form_builds`, which the post-publish half needs. |
| C5, C6 | As planned; small once the schema exists. |
| C7 | As planned, on natural keys. |
| C8 | As planned, expressed as `UpsertOwnerVariant`, `SetFormModules`, `DeployOwnerDrafts`. |
| C10 | Now defined: ODK client `request()`; staged ingestion; provisioning action; poll locking; command clean-up; the security fixes P1–P3. |
| New: C11 | Authoriser contract and deletion-impact queries (U6, U7). |
| D (step 7) | Split into 7a, 7b, 7c, subject to D1 and D2. |

Suggested order: (C1+C2+C9) → C3 → C4 → C5, C6 → C7, C8, C11 → 7a → 7b → C10 → 7c. C10's quick fixes (P1, P2, the `request()` wrapper) can go first, independently, because they are security and robustness fixes that do not depend on the schema.
