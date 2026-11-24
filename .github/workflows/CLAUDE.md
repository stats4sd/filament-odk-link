# CI workflows

- CI job names are per check-run, not per workflow. A job in a new workflow must not reuse a name that the `dev` ruleset already requires (`tests (PHP 8.4)`, `phpstan`, `pint`, `filament tests (PHP 8.4)`, `filament phpstan`, `filament pint`, `reference tests (PHP 8.4)`).
