# Step 4 independent implementation review

**Date**: 2026-09-18

**Branch**: `restructuring-prep-for-ui-split`

**Reviewer**: Independent automated reviewer

**Scope**: Step 4 contracts, support services, provider composition, domain callers, reference wiring and migration documentation against the accepted plan.

## Architectural problems

No remaining actionable boundary problem found. Shipping-domain code no longer depends on host namespaces, Filament/Livewire presentation, role names or namespace scanning. Source boundary enforcement is distinct from the future Step 5 dependency-installation proof.

## Actual bugs found and resolved

1. P2: The initial Filament owner adapter returned a retained tenant after switching to a non-tenant admin panel. Reproduced in the reference app. Fixed by requiring an active tenant-enabled panel, with team-to-admin and no-panel regressions. Repeat reproduction returned a team in the team panel and null in admin/no-panel contexts.
2. P2: Initial actor validation in `generateXlsfile()` ran after processing was enabled, leaving the form stuck when configuration was invalid and no job was queued. Moved validation before synchronization and state changes; regression verifies no dispatch and unchanged processing state.

The repeated correctness review passed with no further actionable findings.

## Code gotchas and validation limits

Local PHPStan nullable-collection inference errors were static-analysis issues, not established runtime defects. They were corrected through precise return annotations and removal of unused traversal, with no new baseline entries. Queue migration requires draining in-flight work and restarting workers; old payload compatibility is not promised. Core-only bootstrap still runs with Filament installed as a package dependency.

The independent acceptance audit identified missing evidence rather than implementation bugs: saved-submission processing, imported language text, provider-registration uniqueness and broader deleted-actor coverage. Those tests were added before the final independent verification recorded in the [change log](../change-logs/2026-09-18-step-4-host-contracts.md).
