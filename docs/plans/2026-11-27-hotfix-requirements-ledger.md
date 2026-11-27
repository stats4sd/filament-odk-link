# Hotfix requirements ledger

**Status**: In Progress

Written 2026-11-27 as part B1 of [2026-11-27-package-review-and-redesign.md](2026-11-27-package-review-and-redesign.md). Every row is open. A row is closed when the C item that owns it merges with a passing test named in the Status column.

## What this is

The `staging-hotfix-20261026` branch and the Groundswell app (`stats4sd/groundswell_platform`) fixed problems found in real use. That code is not ported to `dev`. This file records what those fixes established, so the redesign cannot lose it.

- **Sources covered**: every non-merge package commit `e048b4b..b436265`; `Hot_Fix_List.xlsx` rows (app PRs #68–#85); app PRs #86–#87; the "not fixed" notes in the app's own plan files.
- **Hotfix tests** are cited as `file › title` and live on `origin/staging-hotfix-20261026` under `tests/`. App tests live in the app repo under `tests/Feature/`.
- **Cause** refers to the six causes in the plan: 1 no canonical choice list, 2 template-wide relations include team versions, 3 identity by name or nullable key, 4 side effects in model events that writes bypass, 5 `processing` used as a lock, 6 no rule for locale inheritance or a missing owner. "Feature" means new behaviour, not a defect.
- **Kind**: *Rule* must hold in the new design and needs a test. *Feature* must be re-implemented. *Disappears* exists only because of a cause the redesign removes; the row stays so the removal is checked, and the test named is the one proving the underlying behaviour still holds.

## Adding a row

A hotfix made on the staging branch after 2026-11-27 gets a row here in the same week: the rule in one sentence, the PR and commit, the test that shows it. It is not ported as code.

## Ledger

### Choice lists in the generated form

| ID | Requirement | Kind | Source | Cause | Owner | Status |
| --- | --- | --- | --- | --- | --- | --- |
| L01 | A choice that appears in a list is exported with its labels, whichever module the list is used in. | Rule | `9d124c2`, `e8d6e05`; app #71, #79; `XlsformChoicesExportTest › exports the labelled entry when same-named lists hold the same entry…`, `› does not pick an entry that only has blank placeholder rows…`, `› fills each locale from whichever same-named entry has text for it`, `› exports the labels of the complete entry when a newer duplicate only holds blank rows…` | 1 | C1 | Open |
| L02 | An entry with no label in any language is still exported (ODK reports it; see L31). A locale with no text for an entry is exported blank, never borrowed from another language. | Rule | `e8d6e05`; `XlsformChoicesExportTest › still exports an entry that has no label in any list`, `› leaves a locale blank when no same-named entry has text for it` | 1 | C1, C5 | Open |
| L03 | Choices are exported in template (file) order, not alphabetical. | Rule | `9d124c2`; app #71; `XlsformChoicesExportTest › exports choices in template order, with entries added later by the team last` | 1 | C1 | Open |
| L04 | Entries added by a team come after the global entries of the list, including after a template re-import. | Rule | `3177aac`; app #71; `XlsformChoicesExportTest › exports team entries after the global ones even when the global entries were recreated later` | 1, 3 | C1 | Open |
| L05 | An entry a team removed from its context is left out of that team's generated form, its choice-list media attachments and `getOwnedEntries`. | Rule | `ea6885f`; app #73; `XlsformChoicesExportTest › leaves out an entry the owner removed from context but keeps the others in the list`, `› leaves removed entries out of getOwnedEntries` | 1 | C1 | Open |
| L06 | A removal applies only to the team that made it. | Rule | `XlsformChoicesExportTest › does not apply a removal made by another team` | 1 | C1 | Open |
| L07 | A removal applies to the list as the team sees it: the entry is gone wherever that list is used in the form, and a team-added entry with the same name is gone too. It does not affect an entry with the same name in a different list. | Rule | `ea6885f`; app #68; `XlsformChoicesExportTest › leaves out the entry from every same-named list when it was removed in only one copy`, `› leaves out a team-owned entry that has the same name as a removed entry`, `› does not leave out an entry with the same name in a different list` | 1 | C1 | Open |
| L08 | Removing or restoring an entry marks the team's forms as needing a new draft. | Rule | app #73 (`ChoiceListSiblingService::toggleRemoved`) | 4 | C1, C4 | Open |
| L09 | A list used in several modules is shown to a team as one list, and an edit, addition, deletion or removal made once applies everywhere it is used. | Disappears (C1: one list row) | app #68 (`ChoiceListSiblingService`, `choice-lists:sync-removed-entries`), app plan `2026-10-19-choice-list-duplicate-names` | 1 | C1 | Open |
| L10 | Editing a team entry without changing its name succeeds. Renaming it to a name another entry of the same team already uses in that list is rejected. | Rule | app #81 (`ChoiceListEntryResource` unique rule) | 1, 3 | C1 | Open |
| L11 | The admin choice-list screen and the team choice-list screen act on the same data. Today an admin edit reaches one copy only. | Rule | found in review of app `AdminPanelProvider` vs package `ChoiceListResource` | 1 | C1 | Open |

### Template upload and Replace XLSForm

| ID | Requirement | Kind | Source | Cause | Owner | Status |
| --- | --- | --- | --- | --- | --- | --- |
| L12 | Re-importing a template changes only template-owned (global) content. Team-owned module versions, their rows, lists and entries are untouched. | Rule | `253ebc5` (PR #162); app #75; `XlsformTemplateImportScopeTest › limits the template import scope to the global versions`, `PrepareSurveyRowPathsTest › walks all global versions of a template together and leaves team-owned copies alone`; app `ReplaceXlsformKeepsTeamVersionsTest` | 2 | C2, C3 | Open |
| L13 | Import cleanup never deletes rows of another template (the old cleanup deleted every empty choice list in the database). | Rule | `253ebc5`; Codex B4 | 2 | C3 | Open |
| L14 | After a re-import every existing team form picks up the change: new modules are attached and the form is marked as needing a new draft, without waiting for an unrelated save. | Rule | `253ebc5` (`syncTeamFormsWithTemplate`); app #75 | 4 | C3 | Open |
| L15 | A module added to the template is inserted in each team form directly after the module that precedes it in the template (after that module's local version if it has one, with room left for its own local version; first if it is first). Modules the form already has do not move, and a team's own re-ordering is kept. | Rule | `253ebc5`; `XlsformSyncInsertOrderTest` (6 tests) | 4 | C2, C3 | Open |
| L16 | Importing the same file twice keeps the ids of all global choice entries, with or without a cascade filter, so team-locale strings and removals linked to them survive. | Rule | `f9d12cb` (PR #164); app #78; `XlsformTemplateChoicesImportTest › keeps the ids of all global entries when the same choices are imported twice`, `› keeps a team locale language string linked to the same entry after a second import and cleanup`, `› keeps the ids of entries with a cascade filter, including the same name with different filters` | 3 | C1 | Open |
| L17 | An entry dropped from the file is deleted with its strings; a new one is inserted; an existing one has its properties updated. A team-owned entry with the same name is never matched or changed by the import. | Rule | `XlsformTemplateChoicesImportTest › deletes an entry removed from the file…`, `› updates the properties of an existing entry from the new file`, `› does not match or change a team-owned entry with the same name in the same list` | 3 | C1, C3 | Open |
| L18 | Default-language strings from the template file reach the entry wherever its list is used, on first import, on re-import of the same file and when the text changes. They are never written to a team-owned entry. A survey-row string is written to one row only. | Disappears for the "every same-named list" part (C1); Rule for the rest | `a9cd47e`, `dbc5add` (PR #181); `XlsformTemplateLanguageStringImportTest` (4 tests), `ImportAllLanguageStringsTest` (3 tests) | 1 | C1, C3 | Open |
| L19 | A global entry never ends up without its default-language strings while keeping team strings. | Disappears (C1: strings stored once) | `9d124c2`, `a9cd47e`; `AddMissingChoiceListStringsTest` (7 tests); app repair R1 | 1 | C1 | Open |
| L20 | A module's `can_be_extended` / `can_be_replaced` flags are set from the file only when the file has a `localisable_module` column; otherwise values set in the admin panel are kept. | Rule | `e32757c`; `XlsformModuleImportTest` (3 tests) | — | C3 | Open |
| L21 | A team form's replaceable module is swapped for that form's own local version, never a same-named local version from another template. A module that cannot be replaced stays on its global version. | Rule | `e32757c`; app #71 (repair R2); `XlsformLocaliseModulesTest` (2 tests); Codex B10 | 3 | C2 | Open |
| L22 | Syncing a form with its template works when the owning team has been soft-deleted (or the rule becomes: such forms are skipped, see L38). | Rule | `29243b2` (PR #163); app #77 | 6 | C6 | Open |
| L23 | "Replace XLSForm" behaves identically from the template list and from the template view page, with the same validation and the same guard while an import is running. | Rule | `80b95d2`; issue #124; app #71 | — | C3 | Open |
| L24 | Team clones of a module version have a defined relationship to a template re-import. Today they are snapshots that never receive new or changed questions. | Rule (decision needed in B2) | app plan `2026-11-09-prune-stale-rows-on-replace-xlsform`, "out of scope" | 2 | C2 | Open |
| L25 | A team form is never switched to an empty local version. | Rule | app plan `2026-11-09…` hardening note; app issue `2026-10-28-empty-local-farm-info-module-version`; repair R3 | 3 | C2, C8 | Open |
| L26 | A renamed choice entry keeps its team strings where the file gives a stable way to recognise it; otherwise the limitation is documented. | Rule (decision needed in B2) | app plan `2026-11-11-keep-choice-entry-ids…`, "unavoidable without a stable identifier" | 3 | C1 | Open |

### Form settings

| ID | Requirement | Kind | Source | Cause | Owner | Status |
| --- | --- | --- | --- | --- | --- | --- |
| L27 | `instance_name` and `default_language` are read from the template's settings sheet on upload, in any column order; missing or blank gives null; a new file without the value clears it; a file with no settings sheet keeps the stored values; no other template column is touched. | Feature | `f7f5fe5`, `6ad9f3c`; app #72; `XlsformTemplateSettingsTest` (9 tests); `MigrationsTest` (2 tests) | Feature | C3, C9 | Open |
| L28 | The generated form uses the template's `instance_name` expression when every question it references is in the form (in any module the form uses). Otherwise it uses the default and logs the missing questions. | Feature | `f9fc720`; `XlsformInstanceNameResolverTest` (8 tests), `XlsformSettingsExportTest › exports the template instance_name…`, `› exports the default instance_name…` | Feature | C3 | Open |
| L29 | `default_language` is exported only when the form has a language of that name; otherwise it is left out with a logged warning. The settings row is identical on every call within one build. | Feature | `f9fc720`; `XlsformSettingsExportTest` (4 tests); Codex B13 | Feature | C3 | Open |

### Build and deploy

| ID | Requirement | Kind | Source | Cause | Owner | Status |
| --- | --- | --- | --- | --- | --- | --- |
| L30 | Only one build-and-deploy runs per form at a time. Starting a second one while the first is active does nothing. A claim left by a dead worker expires (15 minutes on the hotfix line). The claim is released on every failure path and held through publish. | Rule | `002b832` (PR #170); app #85; no package tests | 5 | C4 | Open |
| L31 | An edit saved while a deploy is running leaves the form marked as needing a new draft. | Rule | `002b832` (`draft_snapshot_at`, flag cleared at snapshot time) | 5 | C4 | Open |
| L32 | A form exposes its draft state to the host: current, updating, outdated, not deployed, with the time of the deployed snapshot. | Feature | `002b832` (`draftState`, `DRAFT_STATE_*`, `draft_snapshot_at`); app `InitialPilot` | Feature | C4 | Open |
| L33 | Opening a page never builds or deploys forms as a side effect. Deploying a team's outdated drafts is one explicit action that queues one job. | Rule | app #85 (`DeployTeamDraftForms`, `Team::claimDraftForms`) | 4, 5 | C4, C8 | Open |
| L34 | `XlsformDraftWasDeployed` is dispatched when a draft deploy completes. PR #170 stopped this by clearing the flag with a raw update. | Rule | found in review of `002b832` | 4 | C4 | Open |
| L35 | The publish failure path and template deploys use the same lock as form deploys. | Rule | found in review of `002b832` (`PublishXlsformOnOdkCentral::failed`, `XlsformTemplate`) | 5 | C4 | Open |
| L36 | When ODK Central rejects a form for a missing choice label, the user is told which list, which entries and which languages. | Feature | `e8d6e05` (`ChoiceLabelDiagnostics`); `ChoiceLabelDiagnosticsTest` (5 tests) | Feature | C4 | Open |
| L37 | Two jobs never race on the same generated file. | Disappears (C4: single claim) | `e8d6e05` (`XlsformFileSupersededException`, 5-minute sibling window); app `DeployJobGuardsTest` | 5 | C4 | Open |
| L38 | A form whose owner no longer exists (including a soft-deleted team) fails a deploy quietly with a clear log line, not an error notification storm. | Rule | `e8d6e05` (`XlsformOwnerMissingException`); app #79 | 6 | C6 | Open |
| L39 | A draft deploy does not flip `live_needs_update` or announce a publish. | Rule | found in review (`live_needs_update = ! live_needs_update`); Codex B8 | 4 | C4 | Open |

### Languages and translations

| ID | Requirement | Kind | Source | Cause | Owner | Status |
| --- | --- | --- | --- | --- | --- | --- |
| L40 | In a generated form, a team locale shows the team's own text where it has some; otherwise the default locale's text of the same language; otherwise blank. Never another language. Applies to every text type (label, hint, messages), survey rows and choices, and choice-list attachments. Media columns do not fall back. Stored data is not changed. | Rule | `df3a7cf` (PR #168); app #83; `DefaultLocaleFallbackTest` (10 tests) | 6 | C5 | Open |
| L41 | The translation file a team downloads lists only the module versions its form uses (default versions when there is no form), with each question and choice once, without other teams' rows, and without entries the team removed. | Rule | `3eac9df` (PR #169); app #84; app `TranslationExportScopeTest` (10 tests); `XlsformTemplateImportScopeTest › still reaches the team versions through the plain template relations` | 2 | C2, C7 | Open |
| L42 | `end group` and `end repeat` rows are not offered for translation, and strings uploaded for them are ignored. | Rule | `a2b943d`; app #87 (`XlsformTemplateLanguageImport::isEmptyWhen`, repair R4) | — | C7 | Open |
| L43 | A choice used in several modules is translated once. | Disappears (C1) | app issue `2026-11-19-translation-file-duplicate-choice-entries` | 1 | C1, C7 | Open |
| L44 | A translation upload accepts only ids that belong to the uploading team's form. Today it accepts ids from other teams' versions. | Rule | found in review of app `XlsformTemplateLanguageImport`; app plan `2026-11-19…`, "deferred" | 2 | C7 | Open |
| L45 | A locale's "processing" state always ends: it is released once per import on success, failure and retry, and cannot go below zero or stay stuck. | Rule | app #82 (`LocaleImportCounter`, notify jobs) | 4 | C7 | Open |
| L46 | Uploading a file with blank cells over existing translations warns the user with a count before anything is overwritten, and lets them go back. Only files added in the current save are imported. | Feature | app #87 (`TranslationUploadInspector::blankOverExistingCount`) | Feature | C7 | Open |
| L47 | When a translation import completes, the affected module-version locales are marked up to date, the team's forms are marked as needing a new draft, and the user is notified with a link to test the form. | Rule | app `NotifyUserThatLanguageImportIsComplete`; app #85 | 4 | C7 | Open |
| L48 | A queued translation import does not serialise whole template models. | Rule | app plan `2026-10-29-translation-import-and-form-generation-fixes`, phase 1 (not built) | — | C7 | Open |
| L49 | Reference columns in the translation file are visibly read-only. | Rule | app issue `2026-11-17-…reference-columns-look-editable` | — | C7 | Open |

### Host operations on module versions

| ID | Requirement | Kind | Source | Cause | Owner | Status |
| --- | --- | --- | --- | --- | --- | --- |
| L50 | A host can give a team its own editable copy of a module and switch the team's form to it, keeping the module's position. | Feature | app `HddsHints`, `InformedConsent` (`cloneForOwner` + pivot detach/attach) | Feature | C2, C8 | Open |
| L51 | A host can select and re-order the optional modules of a team's form. | Feature | app `OptionalModules` (pivot `sync` with `order`) | Feature | C8 | Open |
| L52 | A host can supply the content of a local module (Groundswell: locations, farm info) through a defined contract, and the form build uses it. | Feature | app `LocationsModuleBuilder`, `FarmInfoModuleBuilder`, `Team::localiseXlsforms` | 3 | C8 | Open |

### Schema

| ID | Requirement | Kind | Source | Cause | Owner | Status |
| --- | --- | --- | --- | --- | --- | --- |
| L53 | Every column and table the core's code uses is created by the core's own migrations (`xlsform_versions.is_draft` exists only in the app's copy; `xlsforms.has_locales` and `locale_xlsform` exist nowhere). A host does not copy package migrations by hand. | Rule | Codex B7; app `database/migrations/03_*`–`05_*` | — | C9 | Open |
| L54 | Migrations run on MySQL as well as SQLite (`016`, `018`, `027` fail on MySQL). | Rule | `apps/reference/README.md`, "Known package issues" | — | C9 | Open |

### Not carried

| Source | Why |
| --- | --- |
| `083ea20` (test factory namespace) | Solved differently on `dev` by the `tests/Database` rename. |
| App repair and backfill commands: `odk:repair-form-deploy-issues` R1–R4, `odk:backfill-template-settings`, `choice-lists:sync-removed-entries` | Repair data shapes that the redesigned schema cannot produce, on a database that is rebuilt at cutover. |
| App #74 (farmer name in farm entity labels), the reversed farm batch in #71 | Client-specific. Stays in the app. |
| App #76, #80, #86, #88 | Docs only. |

## Coverage check

All 19 non-merge commits `e048b4b..b436265` are cited above: `9d124c2` `e32757c` `3177aac` `80b95d2` `f7f5fe5` `6ad9f3c` `f9fc720` `ea6885f` `253ebc5` `29243b2` `f9d12cb` `e8d6e05` `083ea20` `df3a7cf` `3eac9df` `002b832` `a2b943d` `a9cd47e` `dbc5add`.

App PRs cited: #68, #71, #72, #73, #74, #75, #76, #77, #78, #79, #80, #81, #82, #83, #84, #85, #86, #87.
