# Restructuring Step 4: replace host coupling with explicit contracts

**Status**: Not Started

**Date**: 2026-09-18

**Parent**: [RESTRUCTURING.md](../../RESTRUCTURING.md), roadmap Step 4.

**Source baseline**: Local checkout `4accaf6`, branch `chore/agent-metadata-updates`. Recheck against `dev` before implementation; this is a plan, not an implementation or a claim that the current suites pass.

**Proposed implementation branch**: `restructure/host-contracts`, created from current `dev`. Keep the work in one Step 4 PR, with the commit sequence below.

### Progress Log

- 2026-09-18: Inspected the package, reference app, existing tests and restructuring decisions; wrote this implementation plan. Independent plan review completed and its owner-model compatibility finding addressed; document links, code fences and whitespace mechanically checked. No production code changed and no runtime checks run. Planning record: [change log](../change-logs/2026-09-18-step-4-plan.md).

## 1. Outcome and scope

At the end of Step 4, domain code consumes declared host contracts, works with owner and user models outside `App\\Models`, and has no dependency on Filament, the package's UI namespace, Spatie roles, or host namespace scanning. The existing Filament UI continues to run in the reference app through adapters. Step 5 can then move the UI and its adapters without having to discover hidden domain dependencies during extraction.

This is a boundary refactor with narrowly identified behavior changes: invalid integration configuration produces a useful error; language imports use their supplied source path; a locale is not editable without an owner context; administrator notifications can have an empty recipient set. It is not a new authorization system or a redesign of the import/deployment lifecycle.

### Requirements that remain EXACTLY AS-IS

- Keep `stats4sd/filament-odk-link`, `Stats4sd\\FilamentOdkLink`, `filament-odk-link.php`, existing environment key names and the current public plugin entry point. Renames remain part of the later coordinated release.
- Keep one configured deployable-form owner model, existing ownership columns and polymorphic template ownership. `Platform` owns templates through `WithXlsformTemplates`; it must not acquire the full form-owner contract.
- Keep import/deployment queue ordering, processing-flag cleanup, existing domain events, remote ODK behavior and retry behavior. Do not move all model hooks into actions in this PR.
- Keep current query behavior: a selected owner limits forms/submissions and sees shared plus owned choice entries; administrative/CLI execution without an owner remains unscoped as today. These scopes are not authorization checks.
- Keep success notifications broadcast-only where they are broadcast-only today, and failure notifications persisted and broadcast where they do both today. Preserve recipient precedence, notification IDs, payload content, action URLs and processing cleanup order.
- Keep the reference app's host-owned roles, panel setup and published config. Keep Filament dependencies installed until Step 5.

### Work deliberately outside Step 4

Do not extract the second package, publish UI stubs, add translations, rename the repository/packages/config, build release automation, or undertake a broad PHPStan cleanup. Do not fold in the parked MySQL migration fixes, storage typo, offline `PlatformSeeder` behavior, incomplete-template pages, missing submission-route middleware, or the existing `HasXlsforms::country()` relationship issue. Record these as deferred in the tracked roadmap; any dependency on one must be demonstrated by a focused failing test and called out in the implementation PR. Step 4 does not claim to resolve the earlier review's security or data-integrity findings.

## 2. Current coupling inventory

Paths in this table are relative to `packages/odk-link-core/`.

| Area | Current source evidence | Step 4 treatment |
| --- | --- | --- |
| Host imports | `src/Jobs/ImportAllLanguageStrings.php:5` still imports `App\\Models\\Team`, unused. `Dataset` already resolves its owner from config; the two `NotifyUserThat*` jobs already accept Laravel `Authenticatable`. | Remove the actual remaining import; correct the stale roadmap inventory rather than invent replacements for removed code. |
| Hidden owner media convention | `ImportAllLanguageStrings.php:40-47` replaces the supplied path with the owner's `custom_questions` media path. | Use the path supplied by the import pipeline; test owner-backed module imports explicitly. |
| Existing owner contract | `src/Models/OdkLink/Interfaces/WithXlsforms.php:41-77` and `Traits/HasXlsforms.php`. | Promote the existing contract to `Contracts/FormOwner`; preserve its capabilities and distinguish template owners. |
| Model discovery | `src/Services/HelperService.php:25-29,99-113` scans both host and package namespaces. | Explicit configured model registry; retain the helper API as a delegating wrapper. |
| Static submission hook | `src/Jobs/OdkSubmissions/ProcessOdkSubmission.php:23-35` invokes the configured class/method after core processing. | Container-resolved `SubmissionProcessor`; preserve call order and exception propagation. |
| Dead callback config | `submission.foreign_key_process_method` has no runtime caller. | Remove the dead setting and document its removal; do not introduce a new processing phase. |
| Role selection | `src/Jobs/FinishXlsformTemplateImport.php:38,49` and `src/Concerns/NotifiesOnJobFailure.php:43`. | `RoleResolver` selects recipients; host owns the meaning of administrator. |
| Current owner | `HelperService.php:86`; callers in `Xlsform.php:89`, `Submission.php:48`, `ChoiceListEntry.php:38`, `XlsformLanguages/Locale.php:157,214`. | A current-owner contract with headless and Filament implementations. Preserve scopes and fix the directly affected null-owner editability case. |
| Notification presentation | `NotifiesOnJobFailure`, `FinishXlsformTemplateImport`, `Xlsform`, and deployment jobs `DeployDraftXlsformToOdkCentral`, `PublishXlsformOnOdkCentral`, `NotifyUserThatXlsformFileIsUpdated`, `NotifyUserThatXlsformFileIsDeployedAsDraft`. | Plain notification data plus a delivery contract; Filament rendering lives under `src/Filament/`. Remove the unused Filament import in `ChoiceListEntry`. |
| Bootstrap | `src/FilamentOdkLinkServiceProvider.php:65-75` unconditionally registers Filament assets and a Livewire testing mixin. | Separate core registration from UI registration, preserving the existing provider as a compatibility entry point. |
| Test harness | `tests/TestCase.php:34-50` registers Filament providers and currently only configures the owner model explicitly. | Add a minimal core-only harness and a user fixture; retain adapter integration tests with Filament. |

The registry helpers currently have no production caller outside each other. Preserve the existing `countries` lookup and unknown-table `null` behavior covered by `tests/Unit/Services/HelperServiceTest.php`, without designing a new dataset-mapping subsystem.

## 3. Contract design

New contracts live in `src/Contracts/` in the existing package namespace. Use Eloquent model intersections where a value needs both model persistence and a package capability; an interface alone does not guarantee an Eloquent relationship or queue-serializable model.

### 3.1 FormOwner and PlatformUser

`FormOwner` becomes the canonical version of the existing `WithXlsforms` interface. Move its declared methods and documented relationship/property obligations, repair invalid generics touched by the move, and preserve the methods supplied by `HasXlsforms`. Keep `WithXlsforms` as a deprecated interface extending `FormOwner` through the coordinated migration release; there must be one authoritative method list. Update package type hints, reference `Team` and package owner fixtures to use `FormOwner`.

Validate configured owners as concrete `Model` subclasses implementing `FormOwner`. Use `Model&FormOwner` for an actual owner instance and Eloquent-compatible generic bounds for relations. Keep `WithXlsformTemplates` for `Platform` and template/module owner unions. Do not require owners to implement Spatie `HasMedia`; importing a module must not depend on a host's media collection names.

`PlatformUser` represents the host's human user, not the package's ODK Central `AppUser` model. It extends Laravel `Authenticatable` and declares `notify($instance)` and `routeNotificationFor($driver, $notification = null)` with signatures compatible with Laravel's `Notifiable` trait, including its absence of return declarations on these methods. Validate the configured class as an Eloquent `Model` implementing this contract. Document `Notifiable` as the supported implementation, including database/broadcast notification routing. Existing `auth()` results must be validated before they enter notification jobs; a logged-in user of the wrong type is a configuration error, not an administrator fallback.

Use `Model&PlatformUser` for required queue recipients and nullable model intersections where an operation can run without a logged-in actor. Audit callers in `Xlsform`, deployment jobs, workbook exports and failure concerns together. Preserve actor capture when dispatching work; resolve recipient policies and delivery services when handling work. Do not serialize service instances into jobs.

### 3.2 SubmissionProcessor

```php
interface SubmissionProcessor
{
    public function process(Submission $submission): void;
}
```

Bind a configured implementation through the container. Default to `NullSubmissionProcessor`, whose deliberate no-op preserves the optional nature of host post-processing. `ProcessOdkSubmission::handle()` calls core `processSubmission()` first, then the processor once per successful job execution. If core processing throws, do not call the processor; if the processor throws, allow normal job failure/retry behavior. This does not promise exactly-once processing across retries and does not activate post-processing for update paths that do not invoke the callback today.

Remove `submission.process_method` from the new config. If an existing published config supplies either non-empty legacy callback field, reject that configuration at the processing boundary with the key names and migration instruction, rather than silently skipping custom business logic. Hosts move static callback logic into an injectable implementation. Remove the unused foreign-key callback setting and obsolete commented callback code without adding a replacement hook.

### 3.3 RoleResolver

```php
interface RoleResolver
{
    /** @return Collection<int, Model&PlatformUser> */
    public function notificationRecipients(): Collection;
}
```

The name comes from the roadmap; its responsibility is deliberately limited to operational notification recipients. It does not expose role IDs/names, grant access, enumerate tenants, or bypass policies. Empty results are valid. Default to `EmptyRoleResolver`; validate returned recipients and avoid duplicate delivery to the same model identity. The reference app supplies `App\\OdkLink\\ReferenceRoleResolver`, selecting its own `Super Admin` users and returning an empty collection when that role does not exist. A host may instead use a boolean, policy or external permission system without installing Spatie permission.

Replace both success and failure role lookups. Preserve the existing initiating-user-first, administrators-if-no-user behavior in deployment/export failures. Audit `roles.xlsform-admin` before removing it: current runtime code does not use it as an authorization contract, and Step 4 must not turn this historical config field into a new access grant.

### 3.4 CurrentOwnerResolver

```php
interface CurrentOwnerResolver
{
    public function current(): (Model&FormOwner)|null;
}
```

The headless implementation returns `null`. `FilamentCurrentOwnerResolver` under `src/Filament/Support/` reads the active tenant on every call and validates it. It must not cache a tenant in a singleton or read it while the provider is booting. No tenancy/no selected tenant returns `null`; a selected incompatible tenant throws a descriptive integration exception rather than widening the query silently. Require the returned instance to be compatible with the configured `models.form_owner` class as well as `Model&FormOwner`; implementing the interface on an unrelated tenant model is insufficient. Apply this validation at the shared consumption boundary, including results from custom resolvers, so two model classes with the same primary key cannot be confused by `owner_id` scopes. The host remains responsible for tenant-selection middleware and authorization.

Make `HelperService::getCurrentOwner()` a contract-delegating wrapper while updating its return type and callers. Retain the current global scopes for this step. Keep `Locale::status` owner/no-owner behavior and make `isEditable` return `false` when there is no selected owner or creator. Tests must exercise different owners in sequence and queue/CLI execution without a panel. Work that requires a specific owner continues to use the serialized form/model relationship rather than inferring ownership from the current UI.

### 3.5 OperationNotifier

```php
interface OperationNotifier
{
    /** @param Collection<int, Model&PlatformUser> $recipients */
    public function send(OperationNotification $notification, Collection $recipients): void;
}
```

Add an immutable `OperationNotification` value object with nullable ID, title, body, severity, persistence flag, database/broadcast flags and optional action URL. Preserve existing Laravel `HtmlString` bodies where deployment failures use them; do not flatten trusted HTML into escaped text or make every plain-text body trusted HTML. The object contains no Filament actions or builder types. `FilamentOperationNotifier` constructs the existing Filament payload, including database refresh events and the optional view action. The core-only default is `NullOperationNotifier`, explicitly documented as disabled delivery. Empty recipients are a no-op.

Do not change success/failure presentation while introducing this seam. Retain existing failure-message truncation, logging and processing cleanup. In failure handlers, a recipient-resolution or notification exception must not replace the original operation failure or prevent cleanup; log the secondary failure. Prove that behavior with a focused test. Success-notification transport failures retain the current job failure behavior for this refactor; retry/idempotency redesign is separate work.

## 4. Configuration, discovery and bootstrap

### Configuration shape

Keep `models.form_owner` and `models.user_model`, but remove package defaults pointing to `App\\Models`. A fresh package config defaults both to `null`; the reference app's published config explicitly names its `Team` and `User`. Testbench explicitly configures package fixtures. Hosts retain the existing `ODK_FORM_OWNER_MODEL` and `ODK_USER_MODEL` override options.

Add `contracts.submission_processor`, `contracts.role_resolver`, `contracts.current_owner_resolver` and `contracts.operation_notifier`. The first two default to the core no-op implementations. The latter two default to `null`, meaning “use the active integration's default”: core-only boot uses the two null adapters; the existing combined provider uses the Filament adapters. Explicit class strings override these choices. This prevents an existing tenant panel from silently becoming unscoped merely because it has an older published config. Service bindings must be available to workers independently of panel registration and must honor explicit host configuration.

Add `models.registry`, an explicit list of model class strings. Seed it with a reviewed list of the package's concrete discoverable models, including `Country`; consumers add any host models they want table lookup to consider. No filesystem or namespace scan remains. Validate classes as concrete, instantiable Eloquent models without constructor requirements. Detect ambiguous duplicate table mappings and throw a configuration error rather than relying on discovery order. Preserve the public helper return shape, class deduplication and unknown-table `null`; keep the list data-only for config caching. Do not add an unused `ModelRegistry` interface: a concrete `ConfiguredModelRegistry` service is sufficient.

### Validation and binding rules

- Add `Support/ConfiguredModels` to centralize form-owner/user class validation and supply class strings to relations. Validation must not query a database or instantiate services during config loading.
- Validate lazily when a model class or contract is actually used, including before migrations/operations require an owner. Composer package discovery, publishing config, and `config:cache` must work before a host configures its models.
- A missing required model, invalid explicit class, wrong interface or non-instantiable implementation raises a descriptive exception naming the config key. Never catch it and substitute an optional no-op. Missing optional values use only the documented defaults above.
- Resolve implementations through the container so constructor injection works. Resolve contextual values per invocation, never as cached owner/user state. Add a test demonstrating a host implementation with an injected dependency.
- Do not bind `FormOwner` or `PlatformUser` as singleton model instances: their config values identify Eloquent classes. Distinguish those class mappings from service-contract bindings.

### Provider split within the existing package

Introduce `OdkLinkCoreServiceProvider` for config, contracts, services, commands, routes, migrations and domain-event registration. It contains no Filament/Livewire UI registration. Move assets, views and testing mixins into `Filament/OdkLinkFilamentServiceProvider`. Keep the existing auto-discovered `FilamentOdkLinkServiceProvider` as a small compatibility composition root that registers both providers and chooses the integration defaults described above. Preserve public publish tags and the install command, and ensure services, events and commands are registered once.

The new core provider must boot independently. The combined provider remains the single Composer discovery entry until extraction; a headless Testbench harness disables combined-package discovery and explicitly loads the core provider and required non-UI dependencies. Do not accidentally auto-discover the combined provider in that test. The Filament provider/adapters are part of Step 5's move list, together with the existing `Forms/Components`, testing helpers and asset/view registration.

After role/discovery consumers are removed, delete core requirements on `haydenpierce/class-finder` and `spatie/laravel-permission`. Add Spatie permission as an explicit reference-app requirement because that host still uses it. Keep Filament and its media-library plugin until Step 5. Refresh affected package, reference-app and root lockfiles with targeted dependency updates; check that unrelated upgrades did not enter the diff.

## 5. Implementation sequence

Each stage is a commit-sized checkpoint in the same PR. Do not publish an intermediate release.

1. **Establish behavior fixtures and the boundary inventory.** Rebase the plan against `dev`; run baseline core tests, reference tests and PHPStan. Add focused characterization coverage for owner scopes, submission-hook order, notification routing and module source paths. Capture existing failures separately; do not recreate the baseline to hide them. Enumerate current provider/UI dependencies and registry defaults.
2. **Add contracts and configuration support.** Introduce the four roadmap contracts plus current-owner/notifier seams, value object, null implementations, configured-model validation and explicit registry. Migrate `WithXlsforms` to the compatibility extension. Add unit coverage for valid/invalid configuration, non-`App` fixtures, constructor injection and duplicate registry tables.
3. **Separate registration and add UI adapters.** Introduce the core provider, Filament provider and compatibility composition root. Wire headless defaults and combined-provider defaults without double registration. Add core-only boot/config-cache coverage and adapter integration tests. Keep the current reference panels working at this checkpoint.
4. **Replace host conventions in domain flows.** Switch relationships/type hints, helper discovery and current owner, submission callback, admin recipient lookup and all listed notification paths. Remove the `custom_questions` override and verify the supplied path through `HandleXlsformTemplateAdded` into `ImportAllLanguageStrings`. Update failure concerns and every caller as one coherent change. Remove obsolete imports and unused config/commented hooks.
5. **Wire and document the reference host.** Update `app/Models/Team.php`, `app/Models/User.php`, the published config, `app/OdkLink/ReferenceRoleResolver.php`, dependencies and fixtures. Retain its Spatie roles/seeding. Add a small test-local processor implementation to prove container binding and post-processing without introducing a fake production feature. Update `apps/reference/README.md` and the package README with complete minimal host setup and migration examples.
6. **Enforce the boundary and finish dependency cleanup.** Add the architecture rules below, remove unused dependencies, update lockfiles and remove only stale PHPStan baseline entries rendered unnecessary by the change. Extend regression coverage for missing/invalid recipients, sequential owner contexts and headless job execution. Run all gates below.
7. **Review, record and merge.** Use independent review followed by test verification after findings are resolved. Record actual checks and limitations in `docs/change-logs/`; keep the durable host contract and migration instructions in the tracked READMEs. Update the roadmap's Step 4 wording and tick it only after the implementation is complete, with status showing Step 5 next. Open the implementation PR to `dev`; do not merge before required checks and reference tests pass.

## 6. Architecture rules and verification

### Architecture coverage

Extend `tests/ArchTest.php` with enforcement over shipping PHP, including `src`, `database`, config and routes. Do not exempt whole jobs/models/services directories. Tests/fixtures and host application code are separate scopes.

1. Ban `App\\` dependencies from package shipping code, including executable class strings in config; ban package `Tests\\` dependencies from shipping code. Documentation examples may show host classes.
2. Ban `Filament\\`, `Livewire\\` and dependencies on the package's `Filament`, `Forms` and UI-testing namespaces from future-core code. The explicit UI allowlist is `src/Filament/**`, `src/Forms/**`, the existing UI-testing helpers, `src/OdkLinkAdmin.php`, and the compatibility composition root. No domain class is an exception. The new core provider is included in the ban.
3. Ban `HaydenPierce\\ClassFinder`, `Spatie\\Permission`, `Super Admin` and obsolete static submission-hook execution from shipping core. The reference resolver may name its own role.
4. Cover aliases, fully qualified calls and executable string class references. Use Pest dependency checks plus focused source/token checks for cases dependency analysis cannot see; avoid treating comments as executable imports. Demonstrate enforcement by temporarily inserting a forbidden dependency/string in a representative core file, confirm failure, then remove the probe.

These rules establish a source boundary while the UI still ships in the same Composer package. Step 5 must additionally prove core installation and PHPStan without Filament present; Step 4's isolated boot test must not be described as that stronger dependency-installation proof.

### Acceptance checks

| Check | Required evidence |
| --- | --- |
| Alternate host | Testbench owner/user classes outside `App`, without `HasRoles`, support the touched relationships, queued actor serialization and notifications through a fake notifier. No `App\\Models` aliases created to make tests pass. |
| Owner behavior | Two owners plus shared choice entries; selected-owner form/submission filters; no-owner CLI behavior; locale status and false editability without owner; invalid non-null tenant rejected, including another `FormOwner` model class sharing the configured owner's primary key and returned by a custom resolver; switching contexts does not retain the prior owner. |
| Import behavior | Owner-backed module language import reads the supplied workbook path even when the owner lacks `HasMedia`; import events/flags and queue order are preserved. |
| Processor behavior | Runs after core ingestion, receives the expected saved submission, propagates failures, does not run if ingestion fails; null processor works; populated legacy static-hook config produces an actionable error. No new foreign-key callback. |
| Recipient policy | No Spatie installed/registered in the core harness; configured users need no role trait; empty admins and a deleted initiating recipient do not fabricate a replacement user; initiating-user precedence preserved. Specify deleted-model queue behavior explicitly in the affected job tests. |
| Notification delivery | Fake notifier asserts domain payloads; Filament integration tests assert success broadcast and failure database+broadcast payloads, actions and cleanup. Secondary failure during a failure notification cannot mask the original operation failure. |
| Registry | Package Country lookup, explicit host model lookup, unknown table returns null, invalid class and duplicate-table configuration rejected; no namespace discovery. |
| Bootstrap | Core-only provider boots without registered Filament/Livewire providers or roles tables; configuration can be cached; combined provider works in CLI/worker without visiting a panel; no duplicate listeners/commands. |
| Reference app | Existing admin/resource and tenant smoke tests pass; host contract bindings resolve; fixture import completion selects the host's recipients; UI notification adapter and configured current owner work. Keep HTTP/storage faked and avoid live ODK Central. |

Run from the repository root unless the working directory is stated:

```bash
composer test
composer test:reference
composer analyse
composer --working-dir=packages/odk-link-core exec -- pint --test
composer --working-dir=apps/reference exec -- pint --test
composer --working-dir=packages/odk-link-core validate --strict
composer --working-dir=apps/reference validate --strict
composer validate --no-check-publish
git diff --check
```

Run the new focused tests while implementing, then the full suites once the change is coherent. Prove `config:cache` in a disposable test host so local cached config is not left behind. Exercise migration creation with an alternate owner table in the test harness; the reference app's published migration files need no changes unless implementation actually changes migration source. If it does, follow the documented republishing and ordering procedure and include the published copies in the same PR.

Required CI remains the repository's configured core gates, with reference CI also green for this cross-package change. Check live CI state during implementation; the roadmap documents a PHP 8.5 dependency-installation limitation, but this planning task did not verify its current status. Do not broaden this work to repair that matrix entry unless this change causes the failure.

## 7. Migration and completion criteria

The tracked package README must show a complete host setup: `Team implements FormOwner` using `HasXlsforms`, `User implements PlatformUser` using `Notifiable`, explicit model config, a container-injected submission processor, a role-independent recipient resolver and optional current-owner/notifier overrides. The reference README shows the concrete Spatie implementation as a host choice. Document that notification recipient selection does not authorize operations and that no-owner core execution retains the existing unscoped queries.

Include migration instructions for old `WithXlsforms` implementations, removal of static callback settings, explicit model registration, role dependency ownership and the provider/adapters' defaults. Drain in-flight import/deployment jobs before deploying a version that changes serialized job recipient types, then restart workers; do not claim old queued payloads survive changed type declarations without a serialization test. Bundle external-facing renames and these changes into the already-planned coordinated migration release.

Step 4 is complete only when all contracts are used by production callers, the reference host demonstrates the bindings, headless/adapter behavior is covered, architecture checks pass and the two suites plus analysis/formatting gates are green. A file containing four unused interfaces is not completion. Record runtime tests separately from static architecture checks and preserve any remaining deferred issues in `RESTRUCTURING.md`.

This plan and its planning change log live under gitignored `docs/`. The implementation must put durable API/setup documentation in tracked READMEs. The roadmap remains at Step 4 pending until the implementation lands.
