<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Console\Commands;

use Illuminate\Console\Command;
use Maryeperry\Bindle\Support\Environment;

final class BindleInstallCommand extends Command
{
    protected $signature = 'bindle:install {--force : Overwrite existing files}';

    protected $description = 'Publish Bindle config + Dusk stub TestCase and print Vite-plugin wiring instructions.';

    public function handle(Environment $env): int
    {
        $env->assertSafe();

        $this->call('vendor:publish', [
            '--tag' => 'bindle-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'bindle-dusk',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->info('Bindle config + Dusk TestCase published.');

        $this->line('');
        $this->line('Next steps for full coverage (Vue/React/Svelte):');
        $this->line('  1. npm install --save-dev maryeperry-vite-plugin-bindle');
        $this->line('  2. Wire it into vite.config.js:');
        $this->line('         import bindle from \'maryeperry-vite-plugin-bindle\';');
        $this->line('         export default { plugins: [bindle()] };');
        $this->line('  3. Run `npm run build` once; the manifest will be at');
        $this->line('     public/build/bindle-manifest.json.');

        return self::SUCCESS;
    }
}
