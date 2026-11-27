# Review and redesign the ODK Link packages before step 7, carrying the staging hotfixes as requirements

**Status**: In Progress

Done: plan accepted 2026-11-27. Part B is complete and awaiting joint review: the requirements ledger ([2026-11-27-hotfix-requirements-ledger.md](2026-11-27-hotfix-requirements-ledger.md)), the structural review ([docs/code-reviews/2026-11-27-package-structure-review.md](../code-reviews/2026-11-27-package-structure-review.md)), the proposed target design with 31 decisions to take ([2026-11-27-target-design.md](2026-11-27-target-design.md)), and an executable post-publish scenario (`packages/odk-link-core/tests/Unit/Scenarios/`). B3 ran against a faked ODK Central only; no container runtime was available, so the run against a real Central is still to do (review, part 4). `RESTRUCTURING.md` and the step 7 plan are updated. Left: review B together and take the decisions in the target design, then plan C in detail; then C, D and E. Nothing in C has started.

## Context

- `staging-hotfix-20261026` forked from `dev` at `e048b4b` (2026-08-03), before the restructure. A live client system (`stats4sd/groundswell_platform`) is tested on it. `dev` has since been through restructuring steps 2–6.
- Branch size at `origin` tip `b436265` (2026-11-27): 19 non-merge commits, 53 files, +3383/−144. Non-test code is 33 files, +1314/−142. 12 new test files plus additions to 5 existing ones. The local copy of the branch is 6 commits behind `origin`.
- `Hot_Fix_List.xlsx` stops at app PR #85 / package PR #170. Package PR #181 and app PR #87 are newer.
- Decisions with Dave, 2026-11-27:
  - **No parity port.** The hotfix code is not re-applied to `dev`. Hotfix work on Groundswell pauses for about a week while the review and redesign start.
  - The hotfixes are fixed at source by the redesign. Their behaviour is carried over as requirements and tests, not as patches.
  - The client database can be rebuilt, so the data model may change without in-place data migrations.
  - Sequence: B (review) → C (redesign) → D (step 7) → E (Groundswell review, then rework).

## Assessment

### What the hotfixes show

Every package hotfix maps to one of six causes.

| # | Cause | Hotfixes it produced | Kind of patch applied |
| --- | --- | --- | --- |
| 1 | **No canonical choice list.** `choice_lists` is unique per `(xlsform_module_version_id, list_name)`, so the same list exists once per module version and again per team clone. "Same entry" is re-derived by name in five places. | crop_list labels and order, team-after-global order, remove-from-context by name, blank who_family labels, strings copied to every same-named list, repair job (PR #181); app sibling sync (#68) and sibling-aware unique rule (#81) | Pick a representative row at export, merge labels across duplicates, copy strings between copies |
| 2 | **Template-wide relations include team-owned module versions.** `XlsformTemplate::surveyRows()` etc. are `hasManyDeep` over every version. | Replace XLSForm deleting team rows (#162), translation file duplicates (#169) | Parallel relation families `*ForImport()` and `*ForTranslation()`. The unsafe defaults remain. |
| 3 | **Identity by display name or nullable key.** "Local X" versions have `xlsform_module_id = NULL` and are found by name; `cascade_filter` NULL defeats the unique index; the entry unique key omits `owner_id`. | `localiseModules` picking another template's version, entry ids changing on every re-import (#164) | Guards and a hand-written NULL-equals-NULL matcher |
| 4 | **Side effects live in model events, but most writes bypass events** (upserts, bulk updates, pivot syncs). | New modules never reaching team forms, `draft_needs_update` set by hand, PR #170 silently stopping `XlsformDraftWasDeployed` | Manual flag writes at each site |
| 5 | **`processing` boolean used as a lock.** | Duplicate deploys, stuck flags, lost `draft_needs_update` (#170), 5-minute "sibling job" file heuristic (#165) | #170 is a real fix (atomic claim) but covers `Xlsform` only |
| 6 | **No rule for locale inheritance or for a missing owner.** | Blank cells for team locales (#168), soft-deleted team crashing sync (#163) | Fallback inside two exports; null guards at use sites |

### Scope of the rewrite

- The hotfix list is evidence about the half of the workflow that has been exercised by real users: template → modules → choice lists → translations → draft deploy. It says nothing either way about everything after "publish": submissions, entities, datasets, media, polling. That half has only been used by people who know the system.
- It is not evidence-free, though. The static reviews already list serious defects there, unverified in use: Codex B1 (dataset export leaks across owners), B2 (guest-callable submission update route), B5 (edited submissions projected twice), B6 (failed attachments not retried), B9 (soft-deleted submission plus remote edit breaks polling), A4 (submissions processed with the current mapping, not the deployed build's), package-review B15 (#134, processing dispatched before media attached), §1.9 (polling re-downloads everything).
- So B covers both packages in full. For the post-publish half B must also produce the missing evidence, because a design review of code nobody has stressed will repeat the insiders' blind spot. See B3.
- Step 7 stays after the redesign. It freezes UI into host apps, and the UI today has three divergent template-upload paths and a choice-list resource shaped by the per-version copy model.

### What skipping the port costs, and how the plan covers it

- The hotfix code is mostly patches the redesign deletes. Not porting it loses nothing there.
- What must not be lost is what the hotfixes learned. Three kinds:
  - **Behaviour rules** found in real use (team entries after global, fallback to default locale of the same language only, removed entries excluded from forms and translation files, new modules inserted after their predecessor, entry ids stable across re-import, and so on).
  - **Features**, not fixes: `instance_name` and `default_language` from the settings sheet with `XlsformInstanceNameResolver`; `ChoiceLabelDiagnostics` (readable ODK "missing label" errors); the atomic deploy claim with draft states; the Replace XLSForm list action (#124).
  - **Tests**: 12 new package test files, plus app tests of package behaviour (`ReplaceXlsformKeepsTeamVersionsTest`, `DeployJobGuardsTest`, `TranslationExportScopeTest`).
- B1 turns these into a requirements ledger. Each C item takes its rows from the ledger and is not done until each has a passing test in the new design.
- Consequence to accept: `dev` is not deployable for Groundswell until C is complete. The staging line remains the only running line until E.
- Timing risk: one week covers B and the start of C, not C and D. If Groundswell testing resumes before C is done, a new hotfix lands on the staging line as before and adds a row to the ledger. It is never ported as code.

## Sequence

```
B. Review both packages, write the requirements ledger, decide the target design
C. Redesign, one PR per item, driven by the ledger          (roadmap step "6b")
D. Step 7 (stubs), plan refreshed
E.1 Review Groundswell against the new packages
E.2 Groundswell rework, as decided in E.1
```

### B. Review

**B1. Requirements ledger** ([2026-11-27-hotfix-requirements-ledger.md](2026-11-27-hotfix-requirements-ledger.md))
- One row per behaviour rule or feature, from: the spreadsheet rows, every package hotfix commit to `b436265`, app PRs #68–#87, and the "known gaps" recorded in the app's own plans (clones never updated by Replace XLSForm; renamed entries lose team strings; same-named entries translated once per copy; empty Local version breaks deploy).
- Columns: rule, source (PR / commit / test name on the hotfix branch), cause (1–6 or "feature"), C item that will own it, status.
- Rows that only exist because of a cause being removed are marked "disappears with C*n*" with the reason, not dropped silently.

**B2. Structural review of both packages** (`docs/code-reviews/`, with the metadata block from the working patterns)
- Whole of `odk-link-core` and `odk-link-filament`, not just the hotfix area.
- Starts from what exists and is unresolved, to avoid a third audit from zero: package-review §1.1–1.9; Codex A1–A7, C1–C9, B1–B21; open issues #132–#148, #175, #178; the reference app's "Known package issues".
- Decides the target: schema, the public API hosts call, where orchestration lives, the UI seams. The app's current calls are the de-facto API to account for: `claimForDeployment`, `releaseClaim`, `draftState`, `hasActiveClaim`, `draftIsOutdated`, `localiseModules`, `syncWithTemplate`, `deployDraft`, `publishForm`, `cloneForOwner`, `XlsformTemplateTranslationsExport`, direct writes to the `xlsformModuleVersions()` pivot.
- Uses Groundswell as evidence of package gaps (about 2,000 lines of app code are generic, not client-specific). The full app review is E.1.
- One question for D is settled here: whether team-facing UI (choice list entries, translations, draft testing) becomes team-panel stubs. Step 7 currently excludes them, and Groundswell's team UI is entirely app code.

**B3. Evidence for the post-publish half**
- A scripted end-to-end scenario in `apps/reference` against a real ODK Central (container): publish, submit, edit a submission, attach media, delete, re-publish with a changed form, pull, process, entities and datasets, two owners.
- Written from the ODK and README documentation only, deliberately including off-path steps. Failures become findings in B2 and ledger rows.
- If standing up Central is too slow for the week, fall back to recorded HTTP fixtures and note the gap.

**Output of B**: the review, the ledger, and one plan per C item (or one plan with sections) in `docs/plans/`, with schema and API fixed.

### C. Redesign (provisional list; B confirms, changes or extends it)

One PR per item. Tests from the hotfix branch are brought in per item, rewritten against the new design, and the ledger rows ticked.

| Item | Change | What it makes unnecessary |
| --- | --- | --- |
| C1 Canonical choice lists | One list per `(template, list_name)`; module versions reference it. Entries unique on `(choice_list_id, owner, name, cascade_filter)` with `cascade_filter` NOT NULL default `''`. Removal-from-context and language strings attach to the one entry. | Representative-row and label-merge export logic, `AddMissingChoiceListStrings` and its repair half, by-name removal matching, the NULL matcher; app `ChoiceListSiblingService` and sibling unique rule |
| C2 Module version identity | "Local" versions carry `xlsform_module_id` and a `kind` (global, extension, replacement, clone). Template relations return global versions by default. A decided rule for how clones follow a template replace. | `*ForImport()` / `*ForTranslation()` families, name-based lookups and guards |
| C3 Template import as one action | One `ImportTemplateWorkbook` pipeline: choice lists before survey rows, one sweep mechanism, explicit "mark forms outdated and resync". One `ReplaceTemplateFile` action for every UI entry point. Settings-sheet import (`instance_name`, `default_language`) built in. | Manual flag writes, five copies of flag-then-sweep, three divergent UI upload closures |
| C4 Build and deploy state | Atomic claim as the only lock, for `Xlsform` and `XlsformTemplate`. `BuildForm` / `DeployDraft` / `PublishForm` actions with explicit events. No orchestration in `saved` hooks. `ChoiceLabelDiagnostics` kept. | `processing` check-then-set, sibling-file heuristic, `live_needs_update` toggle |
| C5 Label resolution | One resolver for "text for this entry in this locale" (team locale → default locale of the same language). One source for "locales of this form". | Fallback code duplicated across exports |
| C6 Explicit owner context | Jobs and imports take the owner as an argument, never the ambient scope. `FormOwner` defines "owner is gone". | Scattered `withoutGlobalScope('owner')`, owner-missing guards |
| C7 Translation import in the core | The core owns the export format and `processing_count` but not the import. Move it in: import action, upload inspection, completion and failure handling, tracked import runs instead of a counter. | App `XlsformTemplateLanguageImport`, `TranslationUploadInspector`, `LocaleImportCounter`, notify jobs |
| C8 Owner deploy and version-swap operations | "Deploy this owner's outdated drafts" with a host localisation hook; "swap a form's module version" preserving order; a contract for host-built Local modules. | App `DeployTeamDraftForms`, `Team::claimDraftForms()`, pivot code in three app pages |
| C9 Schema owned by the core | Collapse the core migrations into one clean schema, including columns code uses but no migration creates (`xlsform_versions.is_draft`, `xlsforms.has_locales`, `locale_xlsform`). Confirm no other host must upgrade in place. | The app's hand-copied migration folders |
| C10+ Post-publish subdomains | Defined by B2 and B3. Candidates already on record: submission ingestion against the deployed build's mapping, idempotent processing, owner-scoped exports, route middleware, incremental polling, splitting the ODK service. | — |

Order within C: C9 and C1–C2 first (schema), then C3–C6, then C7–C8, then C10+. C9 first means later items write the schema once.

### D. Step 7

- Existing step 7 plan, with its inventory regenerated after C and the team-panel decision from B2 applied.

### E. Groundswell

**E.1 Review** of `groundswell_platform` against the finished packages. Decides E.2. Expected findings, from the read-only look already done:
- Delete: sibling service and rule, translation import stack, deploy orchestration, copied package migrations, repair commands R1–R4 and backfills (they repair data shapes that no longer exist), global `Model::unguard()`.
- Filament: publish the stubs fresh, then re-apply Groundswell customisation on top, rather than adapting the current panels. Confirm per panel in E.1.
- Keep: farm entities and labels, Locations and Farm Info module builders, re-fitted to the C8 contract.
- Host contracts: `Team` / `User` implement `FormOwner` / `PlatformUser`.

**E.2 Rework** as planned in E.1, on a rebuilt database. Retire `staging-hotfix-20261026`.

## Housekeeping

- `UPGRADING.md` entry for each breaking change in C; change log per PR.

## Verification

- B1: every package hotfix commit, every spreadsheet row and every app PR #68–#87 appears in the ledger (scripted check of commit hashes and PR numbers against the file).
- C: an item's PR is mergeable only when its ledger rows each name a passing test; core, Filament and reference suites green; fresh-clone PHPStan for schema and provider changes.
- End of C: ledger has no open rows; the B3 scenario passes end to end in `apps/reference`; by hand in the reference app: upload a template, replace it from list and view, add a team entry, remove an entry from context, upload a translation, deploy a draft twice concurrently, publish.
- E.2: Groundswell's own suite, plus the spreadsheet's "Problem Found" column replayed by hand on staging.
