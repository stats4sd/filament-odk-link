# ODK platform: package, application, or another structure?

**Date:** 2026-09-08
**Branch:** `dev`
**Commit reviewed:** `e048b4b1276c9db61bbf418823df7824fa93ec9c`
**Reviewer:** Codex, with architecture and verification subagents
**Scope:** Distribution and development strategy, informed by this package and the described consuming applications. Consumer repositories, deployment arrangements, and team delivery metrics were not inspected.

## Recommendation

Keep the reusable functionality as a Laravel package, introduce a runnable reference application, and separate the Filament integration from the core. Develop these together in one workspace, initially without requiring a repository split. Avoid making independently customised forks of a full application the standard way to deliver new apps.

The immediate problem is partly the development workflow, but also that the current package boundary is inconsistent. Moving the same code into a full app would improve navigation and make some integrations easier, but would not resolve mutable form definitions, import data loss, implicit deployment side effects, or tenant isolation. Those problems are documented in the [technical review](2026-09-08-package-review.md).

This recommendation assumes your apps will continue to have meaningful differences in domain models, permissions, data processing, front-end pages, and release schedules. If they are predominantly the same product with different branding and a small set of optional features, one configurable application becomes the better option.

## What is making the current arrangement difficult?

- **Core operations depend on the UI context.** `src/Models/OdkLink/Xlsform.php:88` scopes queries through the active Filament tenant, while generation and deployment methods capture `auth()->user()` and send notifications. Calling the same functionality from a worker, app page, or command therefore carries hidden prerequisites.
- **The advertised host contract understates the actual requirements.** `src/Jobs/ImportAllLanguageStrings.php:39` expects a `custom_questions` media collection on an owner; `src/Concerns/NotifiesOnJobFailure.php:43` expects a `Super Admin` role. Neither follows from simply supplying an owner model.
- **Application policy has entered generic processing.** `src/Jobs/FinishChoiceListEntryImport.php:35` treats a module named `location` specially, and line 58 embeds unit-conversion UI wording. Changes to one application's conventions can consequently affect other apps.
- **The UI integration has no clear replacement contract.** `src/OdkLinkAdmin.php:27` discovers the entire resource directory. There is no explicit resource replacement map or feature configuration. The documented tenant plugin is absent from this checkout.
- **There is no demonstrated full consumer workflow in the suite.** Testbench covers useful individual operations, but tests commonly bypass creation hooks, fake the panel registry, and mock processing. A normal app upload → owner customisation → export → deploy journey is not exercised.

These are reasons to improve the boundary and development environment. They are not evidence that Composer distribution itself is unsuitable.

## Comparison of the main approaches

| Approach | Benefits | Costs and failure modes | Fit here |
| --- | --- | --- | --- |
| Current combined package | One dependency distributes shared changes; host apps retain their own models and release schedules; shared UI is immediately available. | Every consumer inherits Filament and application assumptions; unclear extension points encourage copied resources and package patches; cross-repository feature development remains awkward. | Retain temporarily during incremental restructuring, not as the final boundary. |
| Core package + Filament integration + reference app | Shared fixes remain distributable; the same operations support standard and custom UI; reference app makes feature development and end-to-end testing concrete. | Requires a maintained public API, migration policy, integration tests, and ownership of the reference app. Physically splitting packages introduces release coordination. | Recommended target for substantially different apps. |
| Full application with a maintained fork per app | Initial setup is quick; all code is visible together; unrestricted customisation; each deployment is independent. | Every fork owns a divergent copy of core behaviour, schema and UI. Shared fixes require repeated merges or cherry-picks and validation. Overlapping schema changes are particularly difficult. | Reasonable for intentionally independent products that accept ongoing maintenance costs; poor default for a shared core. |
| One configurable application, deployed separately per customer/project | One codebase with independent databases and deployment schedules; branding and enabled features can vary; simpler shared maintenance than forks. | All apps must fit the same domain and extension model. Excessive conditional code and feature combinations become the new complexity. | Strong alternative if most differences are branding, configuration and additive modules. |
| One hosted multi-tenant application | Central upgrades and operations; one maintained UI and schema; immediate distribution of shared features. | Shared release cadence and operational failure domain; tenant isolation, data residency, backup and restore requirements become product responsibilities. Domain variation must fit the same application. | Consider only if these are one product and operational requirements permit it. |
| Separate ODK service with API clients | Central ODK logic can serve non-Laravel apps; independent scaling and deployment. | Adds network failures, API versioning, distributed authorization, asynchronous consistency and more infrastructure. Apps still need custom UI. | Premature without a clear need for independent services or non-Laravel consumers. |

These tradeoffs are an architectural assessment of your stated situation, not measured productivity or cost estimates.

## Why a fork is different from a starter application

A starter application provides initial scaffolding: authentication, panels, branding hooks, example data and package configuration. After creating an app from it, shared runtime functionality still arrives through Composer dependencies. Updating the starter does not automatically update existing applications, but the frequently changing ODK implementation is not copied into each app.

A maintained fork contains its own copy of that implementation. For example, imagine adding revision-aware module selection while three apps have independently changed the owner form editor. A shared package can ship the operation, migrations and default UI together; each consumer upgrades and adapts its custom editor. With full forks, all three copies must receive and reconcile the domain, schema and UI changes. Git can merge compatible text; it cannot determine whether each app still preserves owner customisations or interprets old submissions correctly.

Forking can still be an intentional business decision. It is appropriate when products are expected to diverge permanently, shared upgrades are rare, and each product has a maintenance owner. It should not be treated as a free customisation mechanism.

## Proposed responsibility boundary

| Layer | Owns | Extension approach |
| --- | --- | --- |
| Laravel ODK core | Template/module revisions; owner form composition; validation and compilation; deployment records; Central client; raw submission ingestion; shared entity projection where genuinely common; package migrations. | Explicit application actions, a few container-bound contracts, documented events, and owner-scoped queries. |
| Filament integration | Standard admin/template resources; standard owner workflow if you choose to provide it; reusable actions, schemas and widgets; notifications and panel authorization integration. | Documented resource replacement/configuration, reusable actions, and small schema extension points. |
| Host application | Users and owners; access policy; branding; app-specific entities and reports; submission processing extensions; bespoke pages and deployment configuration. | Calls core actions directly or consumes their events; configures or replaces the standard UI. |
| Reference application | Working installation of both layers; seeded owners and roles; realistic templates; visible queues/status; integration and browser tests. | The supported example and development harness, not another independent product. |

The core may remain Laravel-specific and use Eloquent, queues, storage and the container. A framework-independent domain layer, repository wrapper for every model, or interface for every class would add work without addressing the observed problems. Likewise, separate datasets and choice lists can share an attachment-building abstraction without forcing their different domain semantics into one table.

Laravel explicitly supports packages containing routes, controllers and views; shared UI is not inherently bad package design. Its package mechanisms also support host view overrides. The choice is which behaviour is shared and how it can be extended. [Laravel package development](https://laravel.com/framework/docs/13.x/packages)

For Filament, use the plugin object as the configuration boundary: choose features/resources and supply extensions there. Avoid requiring every host to copy an entire resource just to change one action or field. [Filament plugin configuration](https://filamentphp.com/docs/5.x/plugins/getting-started)

## Improve daily development before changing distribution

Create a reference app next to the package and connect it using a Composer path repository with symlinking enabled. Edit package operations, standard resources and the example host together, and run the actual workflow without repeatedly publishing development releases. Composer supports path repositories for local packages, including symlinking. [Composer path repositories](https://getcomposer.org/doc/05-repositories.md#path)

A practical initial workspace is:

```text
odk-workspace/
  filament-odk-link/        # current package, later with clearer internal layers
  reference-app/            # runnable Laravel/Filament consumer
  app-under-development/    # when a real consumer needs a coordinated change
```

Path repositories belong in the consuming application's root Composer configuration. Keep this a development arrangement: production builds should install tagged versions or another explicitly pinned source revision. Validate the normal dependency installation in CI so symlinks cannot conceal missing files or undeclared dependencies.

A monorepo is optional. It improves atomic review and shared CI if the same team changes all layers frequently, but it does not require a single deployment or mean all apps must be forks. Separate repositories can also work with this workspace. Choose repository layout after establishing the boundary, rather than using it as a substitute for one.

Develop a vertical slice in the reference app, then try it in a second real consumer before declaring the extension API stable. That reveals assumptions that a single demo cannot expose. The reference app should use the same public actions, resources and installation process as consumers.

## Incremental transition

1. **Stabilise current behaviour.** Prioritise tenant leakage, unauthorized callbacks, destructive imports and missing deployment schema from the technical review. Fix the CI version matrix. Add realistic upload/customise/deploy and edited-submission tests.
2. **Make one workflow explicit.** Start with owner form creation and deployment. Introduce actions with explicit owner/actor inputs, after-commit dispatch, operation status and immutable build inputs. Existing model methods can delegate temporarily to preserve consumers.
3. **Define the supported host contract.** Document owner key types, interfaces/traits, authorization responsibilities, storage, queues, user notifications and submission callbacks. Remove implicit host media and role assumptions.
4. **Introduce the reference app and deliberate UI extension points.** Support a second consumer without editing core classes or copying a complete resource for a small customisation.
5. **Separate Composer packages only when useful.** First enforce internal boundaries. Later extract a core package that has no Filament dependency and let `filament-odk-link` depend on it. Preserve existing entry points through an adapter/deprecation period where feasible.
6. **Adopt a release and migration policy.** Use additive upgrade migrations, documented breaking changes, supported dependency versions, representative consumer tests, and a clear owner for shared maintenance. Published migration files and copied views require particular care because existing apps may retain their own copies.

Do not begin with a whole-system rewrite or a large family of tiny packages. A reference app plus clearer actions is useful even if no physical package split ever happens.

## When to choose one full application instead

Prefer one configurable application if a review of your actual consumers shows that their differences are mostly themes, navigation, enabled features and additive pages; their owner and data models have the same meaning; and they can accept a shared core schema and upgrade policy. Separate deployments of that same application can preserve database isolation without maintaining forks.

Keep independently consuming apps if they have materially different workflows, identity models, integration requirements, user experiences or release obligations. A shared ODK package then provides the common capability while each app remains a coherent product.

Before committing to either direction, inventory several recent changes across two or three consumers: which were shared, which required copied package UI, which changed domain behaviour, and which were merely branding. This review establishes real boundary problems, but it cannot establish how similar the unseen applications are. Approaching a stable release is a reason to clarify and test those boundaries now, not by itself a reason to change distribution format.
