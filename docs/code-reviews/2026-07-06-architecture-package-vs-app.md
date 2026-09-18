# Package vs. Application — should `filament-odk-link` stay a package?

**Date**: 2026-07-06
**Author**: Claude (architecture appraisal, companion to [2026-07-06-package-review.md](2026-07-06-package-review.md))
**Question**: The ODK-linking core is shared across several web apps by shipping it as a Laravel/Filament package. Feature work is consistently painful because it is split three ways — server-side (this package), app-specific behaviour (the app), and UI (mostly the app, but partly the package). Should we keep it as a package, or re-architect it — e.g. as a full Laravel app that each new client forks?

---

## TL;DR recommendation

**Keep a shared package, but change its shape and its workflow.** Specifically:

1. **Split the package in two:** a headless **domain/service core** (models, ODK Central client, jobs, import/export, contracts) that stays a strict versioned dependency; and a **Filament UI layer** that is *published into* each app as scaffolding the app owns and edits — the Jetstream/Breeze "starter kit" model, not a locked dependency.
2. **Fix the boundary** the core pretends to have but doesn't (see the coupling findings below) by defining contracts for everything the host supplies.
3. **Adopt a monorepo / path-repository workflow** so core + app are developed together in one PR, killing the split-repo round-trip that is the actual day-to-day pain.

**Do not go to "each app is a fork of a base app."** For something you describe as approaching *stable* and *shared*, forking is the option that looks easiest this quarter and costs the most every quarter after. Reasoning below.

---

## Why the current setup hurts (diagnosis, not just symptom)

The pain isn't intrinsic to "being a package." It comes from three specific, fixable properties this package has today:

### 1. The package boundary is fictional
Despite being config-driven for the owner/user model in places, shipping code hardcodes host-app classes and conventions:

- `use App\Models\Project;` / `App\Models\ProjectActivity` in [Dataset.php](../../src/Models/OdkLink/Dataset.php); `App\Models\Team` in `ImportAllLanguageStrings`/`TestRemoveSub`; `App\Models\User` in `HandleXlsformTemplateAdded` and the notify jobs.
- `HelperService::getModels()` scans the host's `App\Models` namespace at runtime.
- A shipping model imports `Stats4sd\…\Tests\Models\Team`.
- `Role::findByName('Super Admin')` hardcoded in the import chain.
- `HOLPA CHANGE!` comments and app-specific TODOs embedded in the domain models.

So when you "develop a feature," you are constantly crossing a boundary that was never actually drawn. The package can't be reasoned about in isolation because it reaches into the app; the app can't be reasoned about in isolation because behaviour hides in the package. That is the friction — not the `composer.json` indirection.

### 2. The UI ships with no seams
Resources hard-wire concrete schema/table classes with no override hook; widgets are shells whose logic lives in blade views that call a global helper and hardcode URL paths; there is **no translation layer** (`hasTranslations()` is commented out), so every label is hardcoded English. The intended tenant-safe table is even dead-coded. A host that wants a different column, action, or label must subclass or patch. This is why "the UI is mostly in the app" — the packaged UI isn't customisable, so real UI work escapes to the app, and now the same feature is half in each place.

### 3. Every change is a two-repo, two-release dance
`composer require`-ing a versioned package means: change core → tag → bump the app → test → discover a gap → repeat. For a codebase where core and app change *together* on most features, that loop is pure overhead.

Note the direction of causation: (1) and (2) make forking *look* attractive, because a fork lets you ignore the broken boundary and edit anything anywhere. But that's treating the symptom.

---

## The options, honestly compared

### Option A — Keep it exactly as-is (one Filament plugin package, `composer require`d)
**Pros:** no migration; single source of truth for the ODK integration; bug fixed once benefits all apps.
**Cons:** all three pain points above remain. In practice you already work around them by pushing code into the app, which slowly hollows out the "shared" value. Not viable as-is for a *stable* release.

### Option B — Re-architect as a base app; each client is a **fork**
**Pros:** fastest for a brand-new client's *first* bespoke feature — everything is local, edit anything, no package indirection, easy per-app branding/pages.
**Cons (these dominate over time):**
- **Divergence tax compounds.** Every core bugfix or security patch must be cherry-picked/merged into *N* forks that have each drifted. The ODK Central integration is exactly the kind of fiddly, security-relevant, remotely-coupled code where you least want N slightly-different copies. This review already found data-loss and auth bugs (see B3/B14/token-invalidation in the companion doc) — imagine fixing each in five forks that have edited the surrounding lines.
- **No true "stable core."** "Stable" means one authoritative implementation. Forks give you five implementations that were identical once.
- **Merge conflicts scale with customisation**, which is the very thing forking was meant to enable — the more a client customises, the harder the next core merge.
- Testing/CI multiplies; onboarding means "which fork, which drift?"

Forking is the right tool when apps are *mostly different with a little shared*. Your situation is the opposite: *mostly shared with a little different*. Forking optimises for the wrong axis.

### Option C — **Split core (dependency) + UI (published scaffolding)**, monorepo dev  ✅ recommended
Two packages / two layers:

- **`odk-link-core` (headless, versioned dependency):** models, `OdkLinkService` + sub-services, jobs, imports/exports, events, contracts. No Filament, no `App\...`, no hardcoded roles. Everything the host must provide is a config-bound **interface**. This is the genuinely shared, hard-to-get-right code — keep it centralised, versioned, and well-tested.
- **`odk-link-filament` (starter-kit style):** an install command **publishes** the Filament resources/pages/schemas/widgets into `app/Filament/...` of the consuming app. From then on the app *owns* them and edits freely — different columns, actions, labels, branding, extra pages — with no subclassing gymnastics and no package release to change a label. (This is exactly how Jetstream/Breeze/Filament's own scaffolding work: publish once, own thereafter.)

**Pros:**
- The split you actually want: the risky shared logic stays single-source and updatable; the UI is app-owned and infinitely customisable — which is where your UI work already gravitates.
- Bugfixes to the *core* still land once for everyone (unlike forks).
- Clear contracts make features easier to reason about: the app implements interfaces, the core consumes them.
- Per-app branding/pages/features live naturally in the app.

**Cons:**
- Real up-front work: define the contracts, purge `App\...` coupling, and make the UI publishable. (You're paying down debt you already owe — see the companion review.)
- Published UI *does* drift from the package's UI over time — but that drift is opt-in per app and, crucially, **doesn't block core upgrades**, because core has no opinion on the UI.

### Option D — Monorepo with a path-repository (orthogonal, combine with A or C)
Keep the package(s) in the same Git repo as a reference app, consumed via a Composer `path` repository. Develop core + app in one branch/PR; tag for external consumers only when you choose.
**Pros:** eliminates the two-repo/two-release loop — the single biggest daily friction — with almost no architectural change. Works whether you stay Option A or move to C.
**Cons:** consumers not in the monorepo still use tagged releases; needs a tidy release process.

---

## Recommendation in detail

Target state: **Option C + Option D.**

1. **Carve out the headless core.** Move Filament out. In the core, replace every `App\Models\*` reference and `ClassFinder` scan with config-bound contracts:
   - `FormOwner` (what `HasXlsforms` needs), `PlatformUser`, `SubmissionProcessor`, `RoleResolver` (replace `findByName('Super Admin')`).
   - The host binds concrete implementations in config (as it already half-does for `form_owner`/`user_model`).
   This alone removes most of the "where does this behaviour live?" confusion.

2. **Make the UI a starter kit.** Convert the Filament layer to publishable scaffolding (`php artisan odk-link:install --panel=...`). Ship sensible defaults; let the app own the files afterward. Add the translation layer at the same time so published UI is localisable.

3. **Develop in a monorepo.** Put `packages/odk-link-core`, `packages/odk-link-filament`, and a thin demo/reference app in one repo, wired with a `path` repository. Features become one PR. Tag releases for external apps deliberately.

4. **Sequence it behind the bugfixes.** The companion review lists ship-blockers (destructive command, team-panel authorization) and data-integrity bugs. Fix those first on the current structure; they're independent of the packaging decision and shouldn't wait for it.

### What this buys you
- One authoritative, tested ODK integration — no fork divergence on the code you least want copies of.
- UI freedom where you want it (app-owned scaffolding), instead of fighting a rigid packaged UI.
- One-PR feature development via the monorepo.
- A real, enforced boundary, so "server-side vs app vs UI" stops being an every-feature negotiation.

### When Option B (forking) *would* be right
If the apps were diverging so fundamentally that the shared surface is shrinking toward just "talks to ODK Central," and each client's product is genuinely its own thing, then a fork (or a lightweight shared SDK + independent apps) becomes reasonable. Nothing in this codebase suggests that yet — the shared surface is large and the differences are mostly branding/columns/extra pages, which Option C handles without forking.

---

## One-paragraph version for a stakeholder

Keep the ODK integration as a shared, versioned package — that part is worth centralising and is the code you least want duplicated. But split the customisable Filament UI out into publish-once scaffolding each app owns and edits, fix the hidden coupling that currently makes the package reach into `App\Models`, and develop everything in a single monorepo so a feature is one pull request instead of a two-repo release dance. Forking a base app per client would feel faster at first and then quietly cost you every time a core bug or security fix has to be merged into five drifting copies — which, for remote-API integration code, is exactly the situation to avoid.
