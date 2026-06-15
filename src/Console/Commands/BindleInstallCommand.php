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

        $this->info('Bindle config + Dusk scan test published.');

        $this->line('');
        $this->line('To capture real screenshots (drives Chrome via Laravel Dusk):');
        $this->line('  1. composer require --dev laravel/dusk && php artisan dusk:install');
        $this->line('  2. php artisan dusk:chrome-driver --detect');
        $this->line('  3. php artisan serve   (so the app is reachable at APP_URL)');
        $this->line('  4. php artisan bindle:scan --driver=dusk');
        $this->line('');
        $this->line('Without --driver=dusk, bindle:scan uses a no-op browser:');
        $this->line('static component/markdown phases run, but screenshots are placeholders.');

        $this->line('');
        $this->line('Next steps for full coverage (Vue/React/Svelte):');
        $this->line('  1. npm install --save-dev maryeperry-vite-plugin-bindle');

        $localPluginPath = dirname(__DIR__, 3).'/packages/vite-plugin-bindle';
        if (is_dir($localPluginPath)) {
            $this->line('     (working from a local clone of bindle? use the path instead:');
            $this->line('      npm install --save-dev '.$localPluginPath.')');
        }

        $this->line('  2. Wire it into vite.config.js:');
        $this->line('         import bindle from \'maryeperry-vite-plugin-bindle\';');
        $this->line('         export default { plugins: [bindle()] };');
        $this->line('  3. Run `npm run build` once; the manifest will be at');
        $this->line('     public/build/bindle-manifest.json.');

        return self::SUCCESS;
    }
}
