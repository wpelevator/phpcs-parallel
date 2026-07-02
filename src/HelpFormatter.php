<?php

namespace WPElevator\RunParallel;

final class HelpFormatter
{
    public function format(): string
    {
        return <<<'TXT'
run-parallel - run a list of commands, or one command per matched path, in parallel.

Usage:
  run-parallel --command=CMD [--command=CMD ...] [options]
  run-parallel --path-pattern=GLOB --command=TEMPLATE [options]

Arguments:
  COMMAND              Positional shorthand for --command. Quote each command so it
                       stays a single argument.

Options:
  --command=CMD        Command to run. Repeatable. With --path-pattern, each command is
                       rendered once per matched path.
  --path-pattern=GLOB  Match paths to create tasks. Repeatable; comma-separated values are supported.
                       `*` and `?` never match `/`; use `**` to match across directories.
  --processes=N        Number of commands to run at once. Default: auto (CPU core count).
  --cwd=TEMPLATE       Working directory template for each task. Default: invocation directory.
  --label=TEMPLATE     Output label template for each task. Default: {path | dirname | basename};
                       {path | dirname | basename}:{command | slug} when running multiple
                       commands per path; {path | slug} when running a command list.
  --config=PATH        PHP config file for custom filters, variables, and defaults.
  --dry-run            Print the rendered command per task without executing anything.
  --fail-fast          Stop scheduling and terminate running tasks after the first failure.
  -h, --help           Show this help.

Template variables:
  {path}               Matched path, relative to the invocation directory when possible.
                       For a command list, the command string itself.
  {command}            Command template for the current task.
  {index}              Zero-based task index.

Filters:
  dirname, basename, realpath, relative, slug, ext, filename

Examples:
  run-parallel --command='composer lint' --command='composer test' --command='composer analyse'

  run-parallel --path-pattern='packages/*/phpstan.neon' \
    --command='phpstan analyse --configuration={path | realpath} {path | dirname}' \
    --processes=4

  run-parallel --path-pattern='packages/*/composer.json' \
    --command='composer validate' --command='composer test' \
    --cwd='{path | dirname}' --processes=4

Commands are executed directly as argv, not through a shell. Pipes, redirects, and shell expansion are not interpreted.

TXT;
    }
}
