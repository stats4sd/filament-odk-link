# Step 4, explained simply: "plugs, not glue"

**Date**: 2026-11-23

**Covers**: PR #154, restructuring roadmap Step 4 ([plan](../plans/2026-09-18-restructuring-step-4-host-contracts.md), [change log](../change-logs/2026-09-18-step-4-host-contracts.md), [follow-up review](../code-reviews/2026-11-23-step-4-pr-154-review.md))

## The problem, as a picture

Think of the ODK Link package as a kitchen appliance, and each app that uses it as a house.

Before this change, the appliance was **glued into one particular house**. It assumed:

- the house has a room called `App\Models\Team` and a person called `App\Models\User`;
- someone in the house wears a badge that says exactly **"Super Admin"**, from one particular badge-maker (Spatie permissions);
- it could walk around the house opening every cupboard (`App\Models`) to see what's inside;
- it would always be shown on one particular TV (Filament) when it had news to share.

If your house was laid out differently, the appliance broke. Also, you couldn't lift it out to put it in a different kind of house (no TV, a different screen) without ripping off the glue first. That lift-out is Step 5.

## What changed: sockets on the wall

Now the appliance has **standard plugs**, and the house provides **sockets**. The house says "here's my socket"; the appliance never goes looking.

| Socket (contract) | Question the package asks | Who answers |
| --- | --- | --- |
| `FormOwner` | "Which kind of thing owns forms?" | The host's own owner model, such as the reference app's `Team` |
| `PlatformUser` | "Which kind of thing is a person who gets messages?" | The host's own user model |
| `SubmissionProcessor` | "When a survey answer arrives, do you want to do anything extra with it?" | A host class. By default, nothing |
| `RoleResolver` | "Who should hear about admin-level news, such as an import finishing?" | The host decides. The reference app picks its "Super Admin" users; another app could use a checkbox. By default, nobody |
| `CurrentOwnerResolver` | "Which team is the person looking at right now?" | Filament's current tenant, or nobody when running in the background |
| `OperationNotifier` | "How should I deliver this message?" | The Filament pop-up and bell. When running without a screen, nothing |

The package now writes a plain **note** (title, text, how urgent it is, optional link). It hands the note and the list of people to whichever messenger is plugged in. It no longer knows what a Filament notification looks like.

## Other things that moved

- **No more cupboard-rummaging.** Instead of scanning the host's code to find models, there's a written list (`models.registry`) in the config file. If two entries claim the same database table, you get a clear error instead of whichever one was found first.
- **Two power switches instead of one.** The package used to start everything, screen parts included, in one go. Now there's a core switch (`OdkLinkCoreServiceProvider`, no screen) and a screen switch (`OdkLinkFilamentServiceProvider`). The old switch still exists and flips both, so existing apps don't notice.
- **The appliance can't cheat.** Architecture tests now fail the build if core code mentions `App\`, Filament, Livewire, Spatie permissions, the old class scanner or the hard-coded "Super Admin" name.
- **Bad wiring gives a clear message.** If a host points a socket at the wrong kind of class, it gets an error that names the exact config key, not a mysterious crash later.

## What did *not* change

- The package name, namespace, config file name, environment variables and the admin plugin are all the same. (Renames are saved for one later "big moving day" release.)
- Teams still only see their own forms and submissions inside the team panel. Background jobs and the command line still see everything, as before. **None of this is a permission system.** Choosing who gets a message doesn't decide who is allowed to do something.
- Success messages and failure messages look and behave as before: same titles, same pop-up versus saved-in-the-bell behaviour, same "View" link.
- Filament is still installed. Removing it from the core is Step 5.

## Small behaviour changes on purpose

1. If the "Super Admin" badge doesn't exist, import-finished messages go to nobody. Before, the job crashed.
2. Importing a team's custom module reads the file it was actually given. Before, it went looking for a file in a hard-coded media folder.
3. A language/locale can't be edited when no team is selected. Before, the check crashed.
4. If sending a *failure* message itself fails, the original error is still reported and the "processing" flag is still cleared. The messaging problem is only written to the log.

## What the follow-up review fixed (2026-11-23)

1. **Check the wiring before cooking, not after.** If a host still had the old submission-callback settings, the package used to process the survey answer *first* and complain *afterwards*. Each automatic retry then saved the same answer again, creating duplicates. Now it complains before touching anything.
2. **One forgotten bit of glue.** A rarely used event still expected a user type from a different package (`FilamentTeamManagement`) that isn't installed. It now uses the `PlatformUser` socket, and the architecture tests now catch that namespace too.
3. **An empty list shouldn't be silent.** An app with an older copy of the config file has no model list. Lookups used to quietly find nothing; now they say "re-publish the config".

## What a host app has to do when upgrading

1. Add `implements FormOwner` to the owner model (it keeps using `HasXlsforms`) and `implements PlatformUser` to the user model (keep `Notifiable`).
2. Re-publish or update `config/filament-odk-link.php`: set `form_owner` and `user_model` explicitly, keep the `registry` list, and set `contracts.role_resolver` if anyone should get admin messages.
3. Move any old static submission callback into a `SubmissionProcessor` class.
4. If you use Spatie roles, require `spatie/laravel-permission` in the app itself; the package no longer brings it.
5. Let queued import/deploy jobs finish before deploying, then restart the queue workers.

The full instructions are in `packages/odk-link-core/README.md`, and a worked example is in `apps/reference`.

## One thing still in the way

The automated type-checker (PHPStan in CI) currently fails on **every** branch, not because of this work. A spreadsheet library the package depends on (PhpSpreadsheet 1.x) has started labelling itself "deprecated, please upgrade". The real fix is upgrading to the newer Excel library, which is a separate piece of work.
