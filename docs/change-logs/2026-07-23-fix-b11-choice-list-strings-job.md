# Change log — Fix B11: `AddMissingChoiceListStrings` null-deref + non-idempotent backfill

**Date**: 2026-07-23
**Branch**: `pre-publish-fixes`
**Plan**: [2026-07-23-fix-b11-choice-list-strings-job.md](../plans/2026-07-23-fix-b11-choice-list-strings-job.md)
**Executed via**: superpowers:subagent-driven-development (fresh implementer + reviewer subagent per task, final whole-branch review on completion)

## Summary

Fixed finding B11 from the [2026-07-06 package review](../code-reviews/2026-07-06-package-review.md): `src/Jobs/AddMissingChoiceListStrings.php` crashed with a null-dereference when a choice list entry had no translated counterpart elsewhere in the template, and — separately — its selection query permanently excluded a choice list from ever being reprocessed once even one of its entries already had a language string. That meant a mid-loop crash (from the first bug) left every entry after the crash point untranslated forever, with no way to recover on a retry.

## Changes

- `src/Jobs/AddMissingChoiceListStrings.php` — guarded the `$matchingEntry` null case (skip instead of dereference); changed the outer choice-list selection from "qualifies only if **no** entry has strings" to "qualifies if **any** entry still lacks strings"; skip already-populated entries in the loop; replaced the raw multi-row `->insert()` with a per-string `updateOrCreate()` keyed on `(locale_id, language_string_type_id)`, matching the existing idiom in `HasLanguageStrings.php`. The job is now safely re-runnable.
- `tests/Pest.php` — added three fixture helpers (`addChoiceListEntry`, `makeLocale`, `makeLanguageStringType`) following the file's existing DB-layer-insert convention.
- `tests/Unit/Jobs/AddMissingChoiceListStringsTest.php` (new) — two tests reproducing both defects, confirmed RED against the pre-fix job and GREEN after.
- `docs/code-reviews/2026-07-06-package-review.md` — marked B11 as fixed (on-disk only; this file is intentionally untracked — see note below).

## Verification

- `vendor/bin/pest --no-coverage`: 174 passing (172 baseline + 2 new).
- `composer analyse`: no new PHPStan errors introduced.
- Four task-scoped reviews (one per sub-task) and one final whole-branch review all returned Approved, no unresolved Critical/Important findings.

## Notable finding during execution

This repo's `.gitignore` excludes the entire `docs/` directory — `docs/code-reviews/2026-07-06-package-review.md` (and this change log, and the plan above) have never been tracked by git in this repo's history; they're maintained as local, uncommitted documentation. The Task 4 implementer subagent force-added the review doc against that convention; this was caught during the task review pass and corrected with a follow-up commit (`git rm --cached`), restoring the untracked state while keeping the B11 status update on disk.

## Commits

```
1a7c14f test: add locale/language-string-type/choice-list-entry fixture helpers
39685e6 test: reproduce B11 null-deref and non-idempotent backfill in AddMissingChoiceListStrings
68b1616 fix: guard null match and make AddMissingChoiceListStrings re-runnable
4534290 docs: mark B11 fixed in the package review
5e8ff4b chore: untrack code review doc (respect .gitignore docs/ exclusion)
```
