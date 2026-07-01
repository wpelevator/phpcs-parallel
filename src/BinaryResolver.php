<?php

namespace WPElevator\PHPCSParallel;

final class BinaryResolver
{
    public function resolve(string $tool, ?string $configured, string $cwd): string
    {
        $candidates = [];
        if ($configured !== null && $configured !== '') {
            $candidates[] = $configured;
        }

        $candidates[] = $cwd . '/vendor/bin/' . $tool;
        $candidates[] = __DIR__ . '/../../../vendor/bin/' . $tool;
        $candidates[] = $tool;

        foreach ($candidates as $candidate) {
            if (str_contains($candidate, DIRECTORY_SEPARATOR)) {
                $real = realpath($candidate);
                if ($real !== false && is_file($real) && is_executable($real)) {
                    return $real;
                }
                continue;
            }

            return $candidate;
        }

        throw new \RuntimeException('Unable to resolve ' . $tool . ' binary.');
    }
}
