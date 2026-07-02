# pharallel

[![Test](https://github.com/wpelevator/phpcs-parallel/actions/workflows/ci.yml/badge.svg)](https://github.com/wpelevator/phpcs-parallel/actions/workflows/ci.yml)

Run one command per matched path, optionally in parallel.

`pharallel` is useful for PHP monorepos where each package has its own tool config. It discovers paths, renders one command per path, and executes those commands with prefixed output.

## Install

```bash
composer require --dev wpelevator/pharallel
```

## Usage

At its simplest, provide a path pattern and a command template:

```bash
vendor/bin/pharallel \
  --path-pattern='packages/*/phpstan.neon' \
  --command='phpstan analyse --configuration={path} {path | dirname}'
```

Run tasks in parallel with `--processes`:

```bash
vendor/bin/pharallel \
  --path-pattern='packages/*/phpstan.neon' \
  --command='phpstan analyse --configuration={path} {path | dirname} --memory-limit=1G' \
  --processes=4
```

Use `--cwd` when a tool should run from the matched package directory:

```bash
vendor/bin/pharallel \
  --path-pattern='packages/*/composer.json' \
  --command='composer test' \
  --cwd='{path | dirname}' \
  --processes=4
```

When `--cwd` is set, use `{path | realpath}` for file paths that must still point back to the matched file:

```bash
vendor/bin/pharallel \
  --path-pattern='packages/*/phpunit.xml.dist' \
  --command='phpunit --configuration={path | realpath}' \
  --cwd='{path | dirname}' \
  --processes=4
```

Use `--label` when you want custom output prefixes:

```bash
vendor/bin/pharallel \
  --path-pattern='packages/*/phpunit.xml.dist' \
  --command='phpunit --configuration={path}' \
  --label='{path | dirname | basename}' \
  --processes=4
```

Options:

| Option | Description |
| --- | --- |
| `--path-pattern=GLOB` | Match paths to create tasks. Repeatable; comma-separated values are supported. |
| `--command=TEMPLATE` | Command template rendered once per matched path. |
| `--processes=N` | Number of commands to run at once. Default: `1`. |
| `--cwd=TEMPLATE` | Working directory template for each task. Default: invocation directory. |
| `--label=TEMPLATE` | Output label template for each task. Default: `{path | dirname | basename}`. |
| `--config=PATH` | PHP config file for custom filters, variables, and defaults. |

### Template variables

| Variable | Description |
| --- | --- |
| `{path}` | Matched path, rendered relative to the invocation directory when possible. |
| `{index}` | Zero-based task index. |

### Filters

Variables can be piped through filters with `|`. Filters apply left to right, so `{path | dirname | basename}` takes the directory of the matched path, then its last segment.

Example outputs below assume the matched path is `packages/foo/phpcs.xml.dist` and `pharallel` was invoked from `/repo`:

| Filter | Description | Example output |
| --- | --- | --- |
| `dirname` | Parent directory of the path. | `packages/foo` |
| `basename` | Last segment of the path. | `phpcs.xml.dist` |
| `filename` | Last segment without its final extension. | `phpcs.xml` |
| `ext` | Final extension without the dot. | `dist` |
| `realpath` | Absolute path. Relative values are resolved against the invocation directory; if the path does not exist, the joined path is returned unresolved. | `/repo/packages/foo/phpcs.xml.dist` |
| `relative` | Path relative to the invocation directory (`.` for the directory itself). Paths outside it are returned unchanged. | `packages/foo/phpcs.xml.dist` |
| `slug` | Replaces every run of characters other than letters, digits, `.`, `_`, and `-` with a single `-`, then trims leading and trailing dashes. Useful for labels and artifact names. | `packages-foo-phpcs.xml.dist` |

Referencing an unknown variable or filter in a template is an error and fails the run. Custom filters and variables can be added via a [config file](#configuration).

Commands are executed directly as argv, not through a shell. Pipes, redirects, and shell expansion are not interpreted.

### Binary resolution

The first word of `--command` is resolved through the `PATH` environment variable, which child processes inherit from `pharallel`. When `pharallel` runs as a [Composer script](#composer-scripts), Composer prepends the project's `vendor/bin` directory to `PATH` for the duration of the run, so bare binary names like `phpcs` or `phpstan` resolve to the project-local binaries — even when `--cwd` points at a package subdirectory, because the prepended `vendor/bin` path is absolute.

When invoking `vendor/bin/pharallel` directly from the shell, `PATH` is not modified, so bare names resolve to whatever is installed globally. In that case, reference project-local binaries by path:

```bash
vendor/bin/pharallel \
  --path-pattern='packages/*/phpcs.xml.dist' \
  --command='vendor/bin/phpcs --standard={path} {path | dirname}'
```

### Working directory

By default, every command runs in the directory where `pharallel` was invoked. Use `--cwd` to run each command from a different directory — typically the matched package directory via `--cwd='{path | dirname}'`. The template is rendered once per task, and a relative result is resolved against the invocation directory.

`--cwd` changes the child process working directory, but template variables are still rendered relative to the original invocation directory. Use `{path | realpath}` when the child process needs an absolute path.

## Configuration

Use `--config=pharallel.php` to load custom filters, variables, and default option values.

```php
<?php

return [
    'filters' => [
        'package' => static function (string $path): string {
            return basename(dirname($path));
        },
    ],

    'variables' => [
        'php' => PHP_BINARY,
    ],

    'defaults' => [
        'path-pattern' => 'packages/*/composer.json',
        'command' => '{php} vendor/bin/phpunit',
        'cwd' => '{path | dirname}',
        'label' => '{path | package}',
        'processes' => 4,
    ],
];
```

Then run:

```bash
vendor/bin/pharallel --config=pharallel.php
```

CLI options override config defaults, so you can still replace individual values:

```bash
vendor/bin/pharallel \
  --config=pharallel.php \
  --command='composer test'
```

Custom filters receive the current value, the current task, and the invocation working directory:

```php
'filters' => [
    'artifactName' => static function (string $value, \WPElevator\PHPCSParallel\Task $task, string $cwd): string {
        return $task->index . '-' . basename(dirname($value));
    },
],
```

## Examples

### PHPCS

Run PHPCS once per package ruleset:

```bash
vendor/bin/pharallel \
  --path-pattern='packages/*/phpcs.xml.dist' \
  --command='phpcs --standard={path} {path | dirname}'
```

Run PHPCS in parallel and pass PHPCS options directly in the command template:

```bash
vendor/bin/pharallel \
  --path-pattern='packages/*/phpcs.xml.dist' \
  --command='phpcs --standard={path} {path | dirname} -s --report=summary' \
  --processes=4
```

If your project uses multiple PHPCS config names, repeat `--path-pattern`:

```bash
vendor/bin/pharallel \
  --path-pattern='packages/*/.phpcs.xml' \
  --path-pattern='packages/*/phpcs.xml' \
  --path-pattern='packages/*/.phpcs.xml.dist' \
  --path-pattern='packages/*/phpcs.xml.dist' \
  --command='phpcs --standard={path} {path | dirname}' \
  --processes=4
```

### PHPCBF

```bash
vendor/bin/pharallel \
  --path-pattern='packages/*/phpcs.xml.dist' \
  --command='phpcbf --standard={path} {path | dirname}' \
  --processes=4
```

### PHPStan

```bash
vendor/bin/pharallel \
  --path-pattern='packages/*/phpstan.neon' \
  --command='phpstan analyse --configuration={path} {path | dirname} --memory-limit=1G' \
  --processes=4
```

### PHPUnit

```bash
vendor/bin/pharallel \
  --path-pattern='packages/*/phpunit.xml.dist' \
  --command='phpunit --configuration={path}' \
  --processes=4
```

### Composer

`--command` accepts any Composer invocation, not just `composer test`:

```bash
vendor/bin/pharallel \
  --path-pattern='packages/*/composer.json' \
  --command='composer validate' \
  --cwd='{path | dirname}' \
  --processes=4
```

```bash
vendor/bin/pharallel \
  --path-pattern='packages/*/composer.json' \
  --command='composer install --no-interaction' \
  --cwd='{path | dirname}' \
  --processes=4
```

Run a named Composer script the same way:

```bash
vendor/bin/pharallel \
  --path-pattern='packages/*/composer.json' \
  --command='composer run-script lint' \
  --cwd='{path | dirname}' \
  --processes=4
```

## Composer scripts

Composer adds `vendor/bin` to `PATH` when running scripts, so both `pharallel` and the binaries referenced in `--command` can use bare names here (see [Binary resolution](#binary-resolution)):

```json
{
  "scripts": {
    "lint": "pharallel --path-pattern='packages/*/phpcs.xml.dist' --command='phpcs --standard={path} {path | dirname} -s' --processes=4",
    "test:packages": "pharallel --path-pattern='packages/*/phpunit.xml.dist' --command='phpunit --configuration={path}' --processes=4"
  }
}
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
composer test -- --testsuite=Unit
composer test -- --testsuite=Integration
```

Generate PHPUnit coverage reports (requires Xdebug or PCOV):

```bash
composer test:coverage
```

Coverage output is written to the terminal, `tests/coverage/clover.xml`, and `tests/coverage/html`.

## Notes

- Dependency directories are skipped during discovery: `.git`, `vendor`, `node_modules`, `bower_components`.
- Child process output is streamed through Symfony Console and prefixed with the task label.
- The command returns the highest child exit code for normal tool failures. Runtime/tooling errors are promoted to at least exit code `3`.
- File-modifying tools are safe only when matched paths do not overlap.

## License

This project is open-sourced under the MIT License. See [LICENSE](LICENSE) for details.
