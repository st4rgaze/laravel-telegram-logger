# Contributing

Contributions are welcome through pull requests on [GitHub](https://github.com/st4rgaze/laravel-telegram-logger).

## Workflow

1. Fork the repository and create a branch from `main`.
2. Install dependencies with `composer install`.
3. Write a failing test for your change, then make it pass.
4. Run the full check before opening the pull request:

```bash
composer test
```

This runs Rector (`composer refactor` to apply fixes), Pint (`composer lint` to apply fixes), PHPStan at level max with strict rules, 100% type coverage and the Pest suite with at least 95% code coverage.

## Guidelines

- Keep classes small and single-purpose; mark everything that is not public API `@internal`.
- Never log, print or throw anything that contains the bot token.
- Document user-facing changes in `CHANGELOG.md` under `Unreleased`.
