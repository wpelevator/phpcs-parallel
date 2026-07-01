<?php

namespace WPElevator\PHPCSParallel;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process as SymfonyProcess;

final class Process
{
    private string $stdoutBuffer = '';
    private string $stderrBuffer = '';

    public function __construct(
        private readonly SymfonyProcess $process,
        private readonly string $label,
        private readonly ConsoleOutputInterface $output,
    ) {
    }

    public function pump(): void
    {
        $this->writeBufferedOutput($this->process->getIncrementalOutput(), $this->output, $this->stdoutBuffer);
        $this->writeBufferedOutput(
            $this->process->getIncrementalErrorOutput(),
            $this->output->getErrorOutput(),
            $this->stderrBuffer
        );
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    public function exitCode(): int
    {
        $this->pump();
        $this->flushBuffer($this->output, $this->stdoutBuffer);
        $this->flushBuffer($this->output->getErrorOutput(), $this->stderrBuffer);

        return $this->process->getExitCode() ?? 3;
    }

    private function writeBufferedOutput(string $chunk, OutputInterface $output, string &$buffer): void
    {
        if ($chunk === '') {
            return;
        }

        $buffer .= $chunk;
        while (($pos = strpos($buffer, "\n")) !== false) {
            $line = substr($buffer, 0, $pos + 1);
            $buffer = substr($buffer, $pos + 1);
            $output->write('[' . $this->label . '] ' . $line);
        }
    }

    private function flushBuffer(OutputInterface $output, string &$buffer): void
    {
        if ($buffer === '') {
            return;
        }

        $output->write('[' . $this->label . '] ' . $buffer . PHP_EOL);
        $buffer = '';
    }
}
