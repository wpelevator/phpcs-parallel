<?php

namespace WPElevator\PHPCSParallel;

final class HelpFormatter
{
    public function format(string $tool): string
    {
        $binary = $tool === 'phpcbf' ? 'phpcbf-parallel' : 'phpcs-parallel';

        return <<<TXT
{$binary} - run {$tool} once per discovered PHPCS config.

Usage:
  {$binary} [options] [project-dir ...] [-- {$tool} options]

Options:
  --config-pattern=GLOB  Discover configs or project dirs matching the glob. Repeatable.
  --processes=N         Number of {$tool} processes to run at once. Default: 1.
  --bin=PATH            Path to the {$tool} binary. Default: vendor/bin/{$tool}.
  -h, --help            Show this help.

Config resolution:
  Config names are tried in order: .phpcs.xml, phpcs.xml, .phpcs.xml.dist, phpcs.xml.dist.
  Explicit project dirs use their own config or the nearest parent config.
  Project dirs and --config-pattern discovery are combined and deduplicated by project root.

Examples:
  {$binary} --config-pattern='wp-content/plugins/*' --processes=4 -- -s
  {$binary} --config-pattern='wp-content/plugins/*/phpcs.xml.dist' --config-pattern='wp-content/themes/*'
  {$binary} wp-content/plugins/foo wp-content/themes/bar -- --report=summary

TXT;
    }
}
