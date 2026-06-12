<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Storage\Database;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager as LaravelDatabaseManager;

final class ConnectionFactory
{
    public const string CONNECTION_NAME = 'bindle';

    public function __construct(
        private readonly Repository $config,
        private readonly LaravelDatabaseManager $db,
    ) {}

    public function register(): void
    {
        $path = (string) $this->config->get('bindle.database_path');

        if ($path !== ':memory:' && $path !== '') {
            $dir = dirname($path);
            if (! is_dir($dir)) {
                @mkdir($dir, 0o755, true);
            }
            if (! file_exists($path)) {
                @touch($path);
            }
        }

        $this->config->set('database.connections.'.self::CONNECTION_NAME, [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $this->db->purge(self::CONNECTION_NAME);
    }

    public function connection(): Connection
    {
        return $this->db->connection(self::CONNECTION_NAME);
    }
}
