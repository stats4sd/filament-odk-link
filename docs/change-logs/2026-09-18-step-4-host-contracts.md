# Step 4 host contracts implementation

**Date**: 2026-09-18

**Branch**: `restructuring-prep-for-ui-split`

**Status**: Completed; PR review and merge remain for the user.

Implemented the [accepted Step 4 plan](../plans/2026-09-18-restructuring-step-4-host-contracts.md) on the explicitly requested current branch, starting at `cde5795`, which matched `origin/dev`. The plan's suggested new branch was superseded by the user's instruction. Documentation is now tracked following PR #153, despite the plan's original local-only note.

## Changes

- Added canonical `FormOwner`/`PlatformUser`, injectable `SubmissionProcessor`/`RoleResolver`, and current-owner/notifier contracts. Deprecated `WithXlsforms` delegates to the authoritative owner interface. Lazy configured-model validation supports non-`App` models and validates current owners against the configured class.
- Replaced namespace scanning with a validated explicit registry. Split core registration from Filament adapters while preserving the compatibility provider, plugin entry point, install command and publish tags. Core-only boot disables UI/permission provider discovery and uses explicit null defaults.
- Migrated domain relationships, migrations, processor callbacks, actor capture, recipient policy and notification delivery. Preserved queue/event ordering, channels, IDs, HTML bodies, actions and cleanup behavior. Removed the owner media-path override; locale editing now requires an owner/creator. Failure-notification errors cannot interrupt processing cleanup or replace the operation error.
- Updated the reference owner/user, Spatie recipient resolver, published configuration and eleven migration copies. Schema and historical migration filenames did not change. Spatie permission is explicitly host-owned; ClassFinder is removed. Package, app and workspace locks were refreshed without unrelated dependency version upgrades.
- Added domain/headless/adapter/reference tests and shipping-source architecture checks. Added complete host setup, migration instructions and worker-draining guidance to tracked READMEs. Deferred lifecycle/security/data-integrity issues remain deferred in the roadmap.

## Baseline and focused corrections

Baseline core tests passed 185 tests/318 assertions; reference passed 17 with one skipped. Local PHPStan reported three pre-existing nullable-collection return errors in Locale, XlsformTemplate and HelperService, while live dev PHPStan passed. The local analyzer combines null-stripping with invariant Collection stubs; precise covariant return views now express the nullable collections without suppressions. The unused Locale traversal was removed. Twelve stale baseline entries became unnecessary after the contract/type/path changes and were removed; no new suppressions were added.

Reference strict Composer validation previously rejected the unbounded `@dev` requirement. Root/reference path repositories now assign the core a synthetic `dev-dev` version independently of the checkout branch; this is consumer-local metadata, not a published package version. Reference CI now validates strictly. Install dry-run and lock inspection confirmed the intended local package/dependency changes only.

Independent review found two P2 defects during implementation: retained tenants incorrectly scoped a non-tenant panel, and invalid actor configuration could leave a form marked processing before dispatch. Both were fixed and regression-tested. The repeated review found no remaining actionable correctness issues. The acceptance audit strengthened evidence for real persisted submission ingestion, actual imported translation text, provider registration uniqueness and deleted serialized actors across five jobs.

## Verification

| Check | Result |
| --- | --- |
| `composer test` | 230 passed, 634 assertions |
| `composer test:reference` | 22 passed, 48 assertions; one pre-existing skip |
| `composer analyse` | Passed; no errors across 222 shipping PHP files |
| Core and reference `vendor/bin/pint --test` | Passed from their own directories |
| Core and reference `composer validate --strict` | Passed |
| Root `composer validate --no-check-publish` | Passed |
| `git diff --check` | Passed |
| Alternate host and migration | Non-App owner/user fixtures without roles; alternate owner FK verified |
| Headless execution | No registered UI/permission providers or roles table; real persisted ingestion and fake notification delivery verified |
| Configuration caching | Disposable core host cached config twice before required models were configured; cleaned up |
| Architecture enforcement | Forbidden executable host-class string inserted into HelperService caused failure, then removed and check passed |
| Published migration parity | All eleven changed published copies match package source byte-for-byte |

The local Composer installation fails when `exec` uses a relative `--working-dir`; equivalent Pint binaries were run from the proper directories instead. Test suites and PHPStan were run through the required root scripts. Initial sandbox PHPStan socket restriction was resolved by a permitted run; final independent analysis passed normally.

Runtime tests above are separate from source architecture/static checks. Filament remains installed until Step 5; isolated provider boot does not prove installation or analysis with Filament absent. The reference's existing incomplete-template smoke test remains skipped. Laravel missing-model behavior is asserted for the five changed queued actor jobs; the workbook export's different pre-existing serialization mechanism is not claimed compatible with old queued payloads.

Live dev CI was inspected: PHP 8.4 tests, PHPStan, Pint and reference checks passed; the informational PHP 8.5 matrix entry still fails dependency resolution because PhpSpreadsheet 1.x requires PHP below 8.5. This is pre-existing and was not broadened into Step 4. The implementation PR is opened for review and is not merged by this task.
