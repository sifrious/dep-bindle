<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Support;

/**
 * Answers one question: is something actually listening at this URL? Used to
 * turn "the scan produced nothing" into "your app is not running at APP_URL".
 */
class UrlProbe
{
    public function __construct(private readonly float $timeout = 2.0) {}

    /**
     * Null when the host answered; otherwise a human-readable reason.
     */
    public function unreachableReason(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return "[{$url}] is not a URL Bindle can resolve a host from.";
        }

        $scheme = (string) (parse_url($url, PHP_URL_SCHEME) ?: 'http');
        $port = parse_url($url, PHP_URL_PORT);
        $port = is_int($port) ? $port : ($scheme === 'https' ? 443 : 80);

        $errorCode = 0;
        $errorMessage = '';
        $socket = @fsockopen(
            ($scheme === 'https' ? 'ssl://' : '').$host,
            $port,
            $errorCode,
            $errorMessage,
            $this->timeout,
        );

        if ($socket === false) {
            $reason = trim($errorMessage) !== '' ? trim($errorMessage) : "error {$errorCode}";

            return "Nothing is listening on {$host}:{$port} ({$reason}).";
        }

        fclose($socket);

        return null;
    }
}
