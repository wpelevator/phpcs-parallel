# phpcs-parallel

[![Test](https://github.com/wpelevator/phpcs-parallel/actions/workflows/ci.yml/badge.svg)](https://github.com/wpelevator/phpcs-parallel/actions/workflows/ci.yml)

Run [PHPCS](https://github.com/PHPCSStandards/PHP_CodeSniffer/) once per project ruleset, optionally in parallel.

This is useful for monorepos where each package has its own `phpcs.xml.dist`. Instead of merging those rulesets into one PHPCS run, `phpcs-parallel` runs an isolated PHPCS process for each matched config.

## Install

```bash
composer require --dev wpelevator/phpcs-parallel
```

## Usage

Run PHPCS for every matched config:

```bash
vendor/bin/phpcs-parallel --config-pattern='packages/*' --processes=4
```

Pass PHPCS options after `--`:

```bash
vendor/bin/phpcs-parallel --config-pattern='packages/*' --processes=4 -- -s --report=summary
```

Run PHPCBF the same way:

```bash
vendor/bin/phpcbf-parallel --config-pattern='packages/*' --processes=4
```

You can repeat `--config-pattern` or provide comma-separated patterns. Patterns are shell-style globs matched with PHP `fnmatch()`, not regular expressions. They may match either project directories or config files:

```bash
vendor/bin/phpcs-parallel --config-pattern='packages/*' --config-pattern='apps/*/phpcs.xml.dist'

vendor/bin/phpcs-parallel --config-pattern='packages/*,apps/*/phpcs.xml.dist'
```

Each matched config runs as:

```bash
phpcs --standard=/path/to/package/phpcs.xml.dist /path/to/package
```

## Config resolution

When project directories are provided explicitly, each directory uses the first config found in that directory, falling back to the nearest parent config. Config names are checked in this order:

1. `.phpcs.xml`
2. `phpcs.xml`
3. `.phpcs.xml.dist`
4. `phpcs.xml.dist`

When no project directories are provided, the current working directory is recursively searched for matching config files. `--config-pattern` can match either project directory paths or config file paths. Patterns use shell-style glob syntax, for example `packages/*` or `apps/*/phpcs.xml.dist`; regex syntax such as `packages/(foo|bar)` or `packages/.+` is not supported. Passing both project directories and `--config-pattern` combines explicit projects with discovered projects and deduplicates them by project root.

## Composer scripts

```json
{
  "scripts": {
    "lint": "phpcs-parallel --config-pattern='packages/*' --processes=4 -- -s",
    "format": "phpcbf-parallel --config-pattern='packages/*' --processes=4"
  }
}
```

## Example

The [`example`](example) directory contains a small monorepo with two packages, each using a different ruleset (`PSR12` and `WordPress`), wired up via `composer.json` `lint`/`format` scripts:

```bash
cd example
composer install
composer lint
composer format
```

## Development

Run the same checks used by CI:

```bash
composer lint
composer analyse
composer test
```

Run a specific test suite:

```bash
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Integration
```

Generate PHPUnit coverage reports (requires Xdebug or PCOV):

```bash
composer test:coverage
```

Coverage output is written to the terminal, `tests/coverage/clover.xml`, and `tests/coverage/html`.

## Notes

- Dependency directories are skipped during discovery: `.git`, `vendor`, `node_modules`, `bower_components`.
- The wrapper returns the highest child exit code for PHPCS/PHPCBF lint/fix results. Runtime/tool errors are promoted to at least exit code `3`.
- Child process output is streamed through Symfony Console and prefixed with the project label.
- Use explicit project directories instead of patterns if needed: `vendor/bin/phpcs-parallel packages/foo packages/bar`.

## License

This project is open-sourced under the MIT License. See [LICENSE](LICENSE) for details.
