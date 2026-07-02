# Changelog

Notable changes to this project are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

### Added

- `--dry-run` prints the rendered command, working directory, and label per task without executing anything.
- `--fail-fast` stops scheduling and terminates running tasks after the first failure; also available as a `fail-fast` config default.
- `--processes=auto` uses the detected CPU core count (also works as a config default).
- `**` glob support for matching across directory levels, plus `[...]`/`[!...]` character classes.
- A per-task summary (status, duration, exit code) is written to stderr after every run.
- Task label prefixes are colored when the output is a terminal.
- `SIGINT`/`SIGTERM` now stop running child processes before exiting (when the `pcntl` extension is available).

### Changed

- `--processes` now defaults to `auto` (the CPU core count) instead of `1`; pass `--processes=1` for serial execution.
- **Breaking:** glob patterns now use standard semantics — `*` and `?` no longer match across `/`. Previously `packages/*/phpcs.xml` also matched `packages/foo/nested/phpcs.xml`; use `**` for recursive matching.
- **Breaking:** the PHP namespace changed from `WPElevator\PHPCSParallel` to `WPElevator\Pharallel` (affects custom filter signatures referencing `Task`).
- Child process output is now written raw, so tool output containing `<tag>`-like text is no longer mangled by console formatting.
- The echoed `$ command` line only quotes arguments that need quoting.

### Removed

- **Breaking:** `squizlabs/php_codesniffer` is no longer a dependency. PHPCS was never used by pharallel itself; install it in your project when your commands invoke `phpcs`/`phpcbf`.

## 1.1.0 and earlier

Published as `wpelevator/phpcs-parallel`. See the git history for details.
