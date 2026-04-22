<?php

declare(strict_types=1);

namespace Jorgebyte\AntiBugPing\tests;

use Jorgebyte\AntiBugPing\config\AntiBugPingSettings;
use Jorgebyte\AntiBugPing\detection\DesyncDetector;
use Jorgebyte\AntiBugPing\mitigation\MitigationLevel;
use Jorgebyte\AntiBugPing\session\PlayerSessionState;

final class ScoreEngineTest extends TestCase
{
    private function createDetector() : DesyncDetector
    {
        $settings = AntiBugPingSettings::fromArray([
            'score' => [
                'decay-per-tick' => 0.0,
                'thresholds' => [
                    'restrict' => 1.0,
                    'setback' => 2.0,
                    'freeze' => 3.0,
                    'kick' => 4.0,
                ],
            ],
            'thresholds' => [
                'action-score-cooldown-ticks' => 5,
            ],
        ]);

        return new DesyncDetector($settings);
    }

    public function testActionIsIgnoredWhenNetworkIsStable() : void
    {
        $detector = $this->createDetector();
        $state = new PlayerSessionState();

        $result = $detector->onSuspiciousAction($state, 20, 1.5, 'entity-attack');

        $this->assertSame(0.0, $state->getScore());
        $this->assertSame(MitigationLevel::NONE, $result->level);
    }

    public function testCooldownPreventsScoreSpamAndLevelProgresses() : void
    {
        $detector = $this->createDetector();
        $state = new PlayerSessionState();
        $state->markNetworkUnstableUntil(200);

        $first = $detector->onSuspiciousAction($state, 10, 1.2, 'entity-attack');
        $second = $detector->onSuspiciousAction($state, 12, 1.2, 'entity-attack');
        $third = $detector->onSuspiciousAction($state, 13, 1.2, 'block-break');

        $this->assertSame(MitigationLevel::RESTRICT, $first->level);
        $this->assertTrue($second->score < $first->score, 'Score should only decay during cooldown, not increase.');
        $this->assertTrue(($first->score - $second->score) < 0.03, 'Cooldown delta should remain near decay-only range.');
        $this->assertGreaterThan($second->score, $third->score, 'Different reason should still increase score while unstable.');
        $this->assertSame(MitigationLevel::SETBACK, $third->level);
    }
}

