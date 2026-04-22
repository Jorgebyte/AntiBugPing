<?php

declare(strict_types=1);

namespace Jorgebyte\AntiBugPing\tests;

use Jorgebyte\AntiBugPing\config\AntiBugPingSettings;

final class ConfigParserTest extends TestCase
{
    public function testFromArrayAppliesClampAndWorldNormalization() : void
    {
        $settings = AntiBugPingSettings::fromArray([
            'profile' => 'test-profile',
            'sample-size' => 4,
            'min-ping-samples' => 1,
            'min-input-samples' => 999,
            'thresholds' => [
                'high-ping-ms' => 'invalid',
                'critical-ping-ms' => 50,
            ],
            'score' => [
                'thresholds' => [
                    'restrict' => 2.0,
                    'setback' => 2.2,
                    'freeze' => 2.3,
                    'kick' => 2.4,
                ],
            ],
            'excluded-worlds' => ['World', '', 'PvP'],
        ]);

        $this->assertSame('test-profile', $settings->profileName);
        $this->assertSame(6, $settings->sampleSize, 'sample-size must be clamped to min 6.');
        $this->assertSame(3, $settings->minPingSamples, 'min-ping-samples must be clamped to min 3.');
        $this->assertSame(6, $settings->minInputSamples, 'min-input-samples must be capped by sample-size.');

        $this->assertSame(80, $settings->highPingMs, 'Invalid high-ping-ms should fallback to lower bound.');
        $this->assertSame(100, $settings->criticalPingMs, 'critical-ping-ms must respect highPing+20 lower bound.');

        $this->assertTrue($settings->isWorldExcluded('world'));
        $this->assertTrue($settings->isWorldExcluded('PVP'));
    }

    public function testDefaultsAreLoadedWhenKeysAreMissing() : void
    {
        $settings = AntiBugPingSettings::fromArray([]);

        $this->assertSame('balanced', $settings->profileName);
        $this->assertSame(true, $settings->enabled);
        $this->assertSame(20, $settings->sampleSize);
        $this->assertSame(280, $settings->highPingMs);
        $this->assertSame(500, $settings->criticalPingMs);
    }
}

