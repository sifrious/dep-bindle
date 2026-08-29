<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Composition\Artifacts;

use InvalidArgumentException;
use RuntimeException;

final readonly class SafeScaffoldWriter
{
    /** @param list<string> $allowedDirectories */
    public function __construct(
        private string $root,
        private array $allowedDirectories = ['resources/views', 'app/Livewire', 'tests/Feature'],
    ) {}

    /** @param list<GeneratedArtifact> $artifacts */
    public function write(array $artifacts, bool $dryRun = true): ScaffoldPlan
    {
        $validated = [];
        foreach ($artifacts as $artifact) {
            $validated[] = [$artifact, $this->target($artifact->path)];
        }

        $patch = $this->patch($artifacts);
        if ($dryRun) {
            return new ScaffoldPlan($artifacts, $patch, true);
        }

        $created = [];
        try {
            foreach ($validated as [$artifact, $target]) {
                $directory = dirname($target);
                $this->assertNoSymlink($directory);
                if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
                    throw new RuntimeException("Unable to create scaffold directory: {$directory}");
                }
                $this->assertNoSymlink($directory);
                $handle = fopen($target, 'x');
                if ($handle === false) {
                    throw new RuntimeException("Refusing to overwrite existing scaffold: {$artifact->path}");
                }
                if (fwrite($handle, $artifact->contents) === false) {
                    fclose($handle);
                    throw new RuntimeException("Unable to write scaffold: {$artifact->path}");
                }
                fclose($handle);
                $created[] = $artifact->path;
            }

            $manifest = $this->writeManifest($created);
        } catch (\Throwable $exception) {
            foreach ($created as $path) {
                @unlink($this->root.DIRECTORY_SEPARATOR.$path);
            }
            throw $exception;
        }

        return new ScaffoldPlan($artifacts, $patch, false, $manifest);
    }

    private function target(string $relative): string
    {
        if ($relative === '' || str_contains($relative, "\0") || str_starts_with($relative, '/') || str_contains($relative, '\\')) {
            throw new InvalidArgumentException("Unsafe scaffold path: {$relative}");
        }

        $segments = explode('/', $relative);
        if (in_array('..', $segments, true) || in_array('.', $segments, true) || in_array('', $segments, true)) {
            throw new InvalidArgumentException("Unsafe scaffold path: {$relative}");
        }

        $allowed = false;
        foreach ($this->allowedDirectories as $directory) {
            $directory = trim($directory, '/');
            if ($relative === $directory || str_starts_with($relative, $directory.'/')) {
                $allowed = true;
                break;
            }
        }
        if (! $allowed) {
            throw new InvalidArgumentException("Scaffold path is not allowlisted: {$relative}");
        }

        $target = rtrim($this->root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$relative;
        if (file_exists($target) || is_link($target)) {
            throw new RuntimeException("Refusing to overwrite existing scaffold: {$relative}");
        }

        $this->assertNoSymlink(dirname($target));

        return $target;
    }

    private function assertNoSymlink(string $directory): void
    {
        $root = rtrim($this->root, DIRECTORY_SEPARATOR);
        $relative = ltrim(substr($directory, strlen($root)), DIRECTORY_SEPARATOR);
        $cursor = $root;
        foreach ($relative === '' ? [] : explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
            $cursor .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($cursor)) {
                throw new RuntimeException("Scaffold path traverses a symbolic link: {$cursor}");
            }
        }
    }

    /** @param list<GeneratedArtifact> $artifacts */
    private function patch(array $artifacts): string
    {
        $patch = '';
        foreach ($artifacts as $artifact) {
            $lines = explode("\n", $artifact->contents);
            $patch .= "--- /dev/null\n+++ b/{$artifact->path}\n@@ -0,0 +1,".count($lines)." @@\n";
            foreach ($lines as $line) {
                $patch .= '+'.$line."\n";
            }
        }

        return $patch;
    }

    /** @param list<string> $paths */
    private function writeManifest(array $paths): string
    {
        $directory = rtrim($this->root, DIRECTORY_SEPARATOR).'/.bindle/compositions';
        if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create scaffold manifest directory.');
        }
        sort($paths);
        $payload = json_encode(['version' => 1, 'created' => $paths], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        $path = $directory.'/'.hash('sha256', $payload).'.json';
        if (file_put_contents($path, $payload, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write scaffold manifest.');
        }

        return $path;
    }
}
