<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Browser;

/**
 * The result of a single browser capture. Carries enough context for the
 * PageRecorder to notice that a visit didn't land where it was supposed to —
 * e.g. an auth redirect to /login, or a 4xx/5xx error page rendered in place.
 */
final class CapturedResponse
{
    public function __construct(
        public readonly string $html,
        /** The URL the browser actually ended on (after following redirects). */
        public readonly string $finalUrl,
        /** HTTP status of the navigation response, when the driver can read it. */
        public readonly ?int $status = null,
    ) {}
}
