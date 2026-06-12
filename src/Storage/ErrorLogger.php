<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Storage;

use Maryeperry\Bindle\Storage\Models\ErrorLog;

final class ErrorLogger
{
    public const string SEVERITY_WARN = 'warn';

    public const string SEVERITY_ERROR = 'error';

    public const string SEVERITY_FATAL = 'fatal';

    public const string PHASE_ENUMERATE = 'enumerate';

    public const string PHASE_RENDER = 'render';

    public const string PHASE_SCAN = 'scan';

    public const string PHASE_MARKDOWN = 'markdown';

    private ?int $runId = null;

    public function setRunId(int $runId): void
    {
        $this->runId = $runId;
    }

    public function warn(string $phase, string $message, array $context = []): ErrorLog
    {
        return $this->log(self::SEVERITY_WARN, $phase, $message, $context);
    }

    public function error(string $phase, string $message, array $context = []): ErrorLog
    {
        return $this->log(self::SEVERITY_ERROR, $phase, $message, $context);
    }

    public function fatal(string $phase, string $message, array $context = []): ErrorLog
    {
        return $this->log(self::SEVERITY_FATAL, $phase, $message, $context);
    }

    public function log(string $severity, string $phase, string $message, array $context = []): ErrorLog
    {
        return ErrorLog::create([
            'run_id' => $this->runId,
            'phase' => $phase,
            'severity' => $severity,
            'message' => $message,
            'subject_type' => $context['subject_type'] ?? null,
            'subject_id' => $context['subject_id'] ?? null,
            'context' => $context,
        ]);
    }
}
