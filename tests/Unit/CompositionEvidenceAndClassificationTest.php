<?php

declare(strict_types=1);

use Maryeperry\Bindle\Browser\DriverKind;
use Maryeperry\Bindle\Composition\Classification\BehaviorClassification;
use Maryeperry\Bindle\Composition\Evidence\RenderEvidence;

it('classifies all 117 Git behaviors without inventing backend UI', function (): void {
    $path = '/Users/mme/gits/sifrious/vault/thoughts/Projects/Burdgeon/Git Behavior/Git Behavior.md';
    if (! is_file($path)) {
        $this->markTestSkipped('Canonical Burdgeon behavior inventory is unavailable.');
    }
    $classification = BehaviorClassification::fromGitBehaviorMarkdown((string) file_get_contents($path));

    expect($classification->entries)->toHaveCount(117)
        ->and(array_column($classification->entries, 'id'))->toContain('GIT-001', 'GIT-117')
        ->and(array_filter($classification->entries, fn (array $entry): bool => $entry['category'] === 'backend-only'))->not->toBeEmpty()
        ->and($classification->entries[109]['category'])->toBe('compose');
});

it('rejects placeholder render evidence', function (): void {
    new RenderEvidence('change-story', '/stories/change', DriverKind::Placeholder, [], [['behavior_id' => 'GIT-110', 'status' => 'passed']]);
})->throws(InvalidArgumentException::class, 'Placeholder screenshots');

it('accepts linked target results and real desktop and mobile captures', function (): void {
    $desktop = tempnam(sys_get_temp_dir(), 'bindle-desktop-');
    $mobile = tempnam(sys_get_temp_dir(), 'bindle-mobile-');
    file_put_contents($desktop, str_repeat('real desktop capture', 10));
    file_put_contents($mobile, str_repeat('real mobile capture', 10));

    $evidence = new RenderEvidence(
        'change-story',
        '/change-stories/example',
        DriverKind::Dusk,
        ['desktop' => $desktop, 'mobile' => $mobile],
        [['behavior_id' => 'GIT-112', 'status' => 'passed']],
    );

    expect($evidence->toArray()['driver'])->toBe('dusk')
        ->and($evidence->toArray()['test_results'][0]['behavior_id'])->toBe('GIT-112');

    unlink($desktop);
    unlink($mobile);
});
