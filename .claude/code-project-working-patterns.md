code-project-working-patterns

# Work Patterns

1. Save all accepted plans to docs/plans.
2. Every plan file must have a **Status: ** line indicating the current status of the plan. Either: "Not Started", "In Progress", "Completed", "Abandoned". In progress plans should have a short paragraph explaining what has been done and what is left to do. Abandoned plans must have a short paragraph explaining the reason for not completing the plan. Completed plans must have a link to a corresponding change log file.
3. After completing a piece of work (e.g., finishing a plan), save a summary of the changes into docs/change-logs. Change logs files linked to a plan should reference the plan file in the intro.
4. Code reviews conducted via the /code-review skill or via explicit "feature review" requests should be saved to docs/code-reviews, with the following metadata at the top of the document:
    **Date**: 2026-05-27
    **Branch**: `review-roles-and-permissions`
    **Reviewer**: Claude (automated multi-angle review)
    **Scope**: 56 files, 1,945 insertions — new Spatie role/permission model with 5 roles and policies across three Filament panels.
5. If a user asks you to capture, note or save an issue, create a new issue on Github.

