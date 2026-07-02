<?php

namespace WPElevator\RunParallel;

final class CommandTemplate
{
    public function __construct(private readonly TemplateRenderer $templateRenderer)
    {
    }

    /** @return list<string> */
    public function render(string $template, Task $task, string $invocationCwd): array
    {
        $tokens = $this->tokenize($template);
        $command = [];

        foreach ($tokens as $token) {
            $rendered = $this->templateRenderer->render($token, $task, $invocationCwd);
            if ($rendered !== '') {
                $command[] = $rendered;
            }
        }

        if ($command === []) {
            throw new \InvalidArgumentException('Rendered command is empty.');
        }

        return $command;
    }

    /** @return list<string> */
    private function tokenize(string $command): array
    {
        $tokens = [];
        $current = '';
        $quote = null;
        $escaped = false;
        $braceDepth = 0;
        $length = strlen($command);

        for ($i = 0; $i < $length; $i++) {
            $char = $command[$i];

            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                    continue;
                }
                $current .= $char;
                continue;
            }

            if ($char === '{') {
                $braceDepth++;
                $current .= $char;
                continue;
            }

            if ($char === '}') {
                $braceDepth = max(0, $braceDepth - 1);
                $current .= $char;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if (ctype_space($char) && $braceDepth === 0) {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }
                continue;
            }

            $current .= $char;
        }

        if ($escaped) {
            $current .= '\\';
        }

        if ($quote !== null) {
            throw new \InvalidArgumentException('Unclosed quote in command template.');
        }

        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }
}
