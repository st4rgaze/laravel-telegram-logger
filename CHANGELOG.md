# Changelog

All notable changes to this project are documented in this file. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `telegram` log driver for Laravel 12 and 13 on PHP 8.3+.
- Queued delivery (default) through a job chain with rate-limit aware retries, or sync delivery.
- Token safety: a dedicated event-less HTTP client, token-free exceptions, no token in queue payloads, token masked in outgoing text.
- Deduplication and per-chat rate limiting with a suppressed-message counter.
- Redaction by key, by regex pattern (email by default) and placeholder SQL for `QueryException`.
- UTF-8 and HTML safe message splitting, with overflow and stack traces attached as a text file.
- Request context (URL, method, route, user id; IP opt-in).
- `MessageFormatter` contract and `TelegramText` builder for custom layouts.
- `telegram-logger:test` diagnostic command.
