<?php

declare(strict_types=1);

namespace Maryeperry\Bindle;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use Maryeperry\Bindle\Console\Commands\BindleErrorsCommand;
use Maryeperry\Bindle\Console\Commands\BindleInstallCommand;
use Maryeperry\Bindle\Console\Commands\BindleResetCommand;
use Maryeperry\Bindle\Console\Commands\BindleScanCommand;
use Maryeperry\Bindle\Generators\MarkdownGenerator;
use Maryeperry\Bindle\Phrases\Dictionary;
use Maryeperry\Bindle\Pipeline\ScanPipeline;
use Maryeperry\Bindle\Routes\RouteEnumerator;
use Maryeperry\Bindle\Scanners\AlpineScanner;
use Maryeperry\Bindle\Scanners\BladeScanner;
use Maryeperry\Bindle\Scanners\InertiaScanner;
use Maryeperry\Bindle\Scanners\LivewireScanner;
use Maryeperry\Bindle\Scanners\ManifestScanner;
use Maryeperry\Bindle\Storage\Database\ConnectionFactory;
use Maryeperry\Bindle\Storage\Database\DatabaseManager;
use Maryeperry\Bindle\Storage\ErrorLogger;
use Maryeperry\Bindle\Support\Environment;

final class BindleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bindle.php', 'bindle');

        // ─────────────────────────────────────────────────────────────────────
        // PRODUCTION INTERLOCK — guard #1.
        // If the app is in production we register nothing. Commands disappear
        // entirely; the package becomes inert.
        // ─────────────────────────────────────────────────────────────────────
        if ($this->app->environment('production')) {
            return;
        }

        $this->app->singleton(Environment::class, function ($app) {
            return new Environment($app, $app->make(Repository::class));
        });

        $this->app->singleton(ConnectionFactory::class);
        $this->app->singleton(DatabaseManager::class, function ($app) {
            return new DatabaseManager(
                $app->make(ConnectionFactory::class),
                $app->make(Repository::class),
            );
        });

        $this->app->singleton(ErrorLogger::class);
        $this->app->singleton(Dictionary::class, function () {
            return Dictionary::fromDefaults();
        });

        $this->app->singleton(RouteEnumerator::class);
        $this->app->singleton(BladeScanner::class);
        $this->app->singleton(LivewireScanner::class);
        $this->app->singleton(AlpineScanner::class);
        $this->app->singleton(InertiaScanner::class);
        $this->app->singleton(ManifestScanner::class);
        $this->app->singleton(MarkdownGenerator::class);

        $this->app->singleton(ScanPipeline::class);

        $this->app->singleton(Bindle::class, function ($app) {
            return new Bindle(
                $app->make(Environment::class),
                $app->make(DatabaseManager::class),
                $app->make(ScanPipeline::class),
            );
        });
    }

    public function boot(): void
    {
        // Guard #1 (again): if the app is in production, do nothing on boot
        // either — no config publish, no commands.
        if ($this->app->environment('production')) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/bindle.php' => config_path('bindle.php'),
        ], 'bindle-config');

        $this->publishes([
            __DIR__.'/../resources/stubs/BindleScanTest.php.stub' => base_path('tests/Browser/BindleScanTest.php'),
        ], 'bindle-dusk');

        if ($this->app->runningInConsole()) {
            $this->commands([
                BindleInstallCommand::class,
                BindleScanCommand::class,
                BindleResetCommand::class,
                BindleErrorsCommand::class,
            ]);
        }
    }
}
