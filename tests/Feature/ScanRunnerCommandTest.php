<?php

declare(strict_types=1);

use Maryeperry\Bindle\Browser\DriverKind;
use Maryeperry\Bindle\Http\ScanRunner;

beforeEach(function (): void {
    $this->logPath = tempnam(sys_get_temp_dir(), 'bindle-log-').'.log';
    config()->set('bindle.log_path', $this->logPath);
});

afterEach(function (): void {
    if (isset($this->logPath) && is_file($this->logPath)) {
        @unlink($this->logPath);
    }
});

it('always names the driver in the spawned command', function (): void {
    $command = app(ScanRunner::class)->buildCommand();

    expect($command)->toContain("bindle:scan --driver='null'");
});

it('passes a dusk request through to the subprocess', function (): void {
    $command = app(ScanRunner::class)->buildCommand(null, false, DriverKind::Dusk);

    expect($command)->toContain("--driver='dusk'");
});

it('sends scan output to a log rather than discarding it', function (): void {
    $command = app(ScanRunner::class)->buildCommand();

    expect($command)->not->toContain('/dev/null')
        ->and($command)->toContain($this->logPath);
});

it('keeps the route and fresh flags alongside the driver', function (): void {
    $command = app(ScanRunner::class)->buildCommand('dashboard', true, DriverKind::Dusk);

    expect($command)->toContain('--fresh')
        ->and($command)->toContain("--route='dashboard'")
        ->and($command)->toContain("--driver='dusk'");
});

it('reads back the tail of the launcher log', function (): void {
    file_put_contents($this->logPath, "line one\nline two\nline three\n");

    expect(app(ScanRunner::class)->tailLog(2))->toBe("line two\nline three");
});

it('returns an empty tail when nothing has been logged', function (): void {
    @unlink($this->logPath);

    expect(app(ScanRunner::class)->tailLog())->toBe('');
});
