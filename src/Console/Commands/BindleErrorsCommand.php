<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Console\Commands;

use Illuminate\Console\Command;
use Maryeperry\Bindle\Storage\Database\DatabaseManager;
use Maryeperry\Bindle\Storage\Models\ErrorLog;
use Maryeperry\Bindle\Support\Environment;

final class BindleErrorsCommand extends Command
{
    protected $signature = 'bindle:errors
                            {--severity= : Filter by severity (warn|error|fatal)}
                            {--phase= : Filter by phase (enumerate|render|scan|markdown)}
                            {--run= : Filter by run id}';

    protected $description = 'Print the contents of the Bindle errors table.';

    public function handle(Environment $env, DatabaseManager $db): int
    {
        $env->assertSafe();
        $db->ensureSchema();

        $query = ErrorLog::query();
        if ($s = $this->option('severity')) {
            $query->where('severity', $s);
        }
        if ($p = $this->option('phase')) {
            $query->where('phase', $p);
        }
        if ($r = $this->option('run')) {
            $query->where('run_id', (int) $r);
        }

        $rows = $query->orderBy('occurred_at')->get();

        if ($rows->isEmpty()) {
            $this->info('No matching errors recorded.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Run', 'Phase', 'Severity', 'Subject', 'Message'],
            $rows->map(fn (ErrorLog $e) => [
                $e->id,
                $e->run_id ?? '-',
                $e->phase,
                $e->severity,
                ($e->subject_type ?? '-').'#'.($e->subject_id ?? '-'),
                substr($e->message, 0, 80),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
