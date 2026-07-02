<?php

namespace WPElevator\Pharallel;

final class HelpFormatter
{
    public function format(): string
    {
        return <<<'TXT'
pharallel - run a list of commands, or one command per matched path, in parallel.

Usage:
  pharallel --command=CMD [--command=CMD ...] [options]
  pharallel --path-pattern=GLOB --command=TEMPLATE [options]

Arguments:
  COMMAND              Positional shorthand for --command. Quote each command so it
                       stays a single argument.

Options:
  --command=CMD        Command to run as its own task. Repeatable when no --path-pattern
                       is given. With --path-pattern, provide exactly one command; it is
                       a template rendered once per matched path.
  --path-pattern=GLOB  Match paths to create tasks. Repeatable; comma-separated values are supported.
                       `*` and `?` never match `/`; use `**` to match across directories.
  --processes=N        Number of commands to run at once. Default: auto (CPU core count).
  --cwd=TEMPLATE       Working directory template for each task. Default: invocation directory.
  --label=TEMPLATE     Output label template for each task. Default: {path | dirname | basename},
                       or {path | slug} when running a command list.
  --config=PATH        PHP config file for custom filters, variables, and defaults.
  --dry-run            Print the rendered command per task without executing anything.
  --fail-fast          Stop scheduling and terminate running tasks after the first failure.
  -h, --help           Show this help.

Template variables:
  {path}               Matched path, relative to the invocation directory when possible.
                       For a command list, the command string itself.
  {index}              Zero-based task index.

Filters:
  dirname, basename, realpath, relative, slug, ext, filename

Examples:
  pharallel --command='composer lint' --command='composer test' --command='composer analyse'

  pharallel --path-pattern='packages/*/phpstan.neon' \
    --command='phpstan analyse --configuration={path | realpath} {path | dirname}' \
    --processes=4

  pharallel --path-pattern='packages/*/composer.json' \
    --command='composer test' --cwd='{path | dirname}' --processes=4

Commands are executed directly as argv, not through a shell. Pipes, redirects, and shell expansion are not interpreted.

TXT;
    }
}
