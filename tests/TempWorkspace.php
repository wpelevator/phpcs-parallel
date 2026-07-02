<?php

declare(strict_types=1);

namespace WPElevator\Pharallel\Tests;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class TempWorkspace
{
    public readonly string $dir;

    public function __construct(string $identifier)
    {
        $this->dir = sys_get_temp_dir() . '/' . $identifier . '-' . bin2hex(random_bytes(6));

        if (! mkdir($this->dir, 0777, true)) {
            throw new RuntimeException('Unable to create workspace directory: ' . $this->dir);
        }
    }

    public function path(string $relative = ''): string
    {
        return $relative === '' ? $this->dir : $this->dir . '/' . $relative;
    }

    public function writeFile(string $relative, string $contents = ''): string
    {
        $path = $this->path($relative);
        $parent = dirname($path);

        if (! is_dir($parent)) {
            mkdir($parent, 0777, true);
        }

        file_put_contents($path, $contents);

        return $path;
    }

    public function writeExecutable(string $relative, string $contents): string
    {
        $path = $this->writeFile($relative, $contents);
        chmod($path, 0755);

        return $path;
    }

    public function cleanup(): void
    {
        if (! file_exists($this->dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->dir);
    }
}
