<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Console\Commands;

use Illuminate\Console\Command;
use Maryeperry\Bindle\Storage\Database\DatabaseManager;
use Maryeperry\Bindle\Support\Environment;

final class BindleResetCommand extends Command
{
    protected $signature = 'bindle:reset {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe the Bindle SQLite database and output directory.';

    public function handle(Environment $env, DatabaseManager $db): int
    {
        $env->assertSafe();

        if (! $this->option('force') && ! $this->confirm('This will delete all Bindle scan data and output. Continue?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $db->ensureSchema();
        $db->truncateAll();

        $outputPath = (string) config('bindle.output_path');
        if (is_dir($outputPath)) {
            $this->recursiveRemove($outputPath);
            $this->info("Removed output directory at {$outputPath}.");
        }

        $this->info('Bindle has been reset.');

        return self::SUCCESS;
    }

    private function recursiveRemove(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path.'/'.$entry;
            is_dir($full) ? $this->recursiveRemove($full) : @unlink($full);
        }
        @rmdir($path);
    }
}
