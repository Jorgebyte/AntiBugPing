<?php

declare(strict_types=1);
/**
 * This file is part of the AntiBugPing plugin for PocketMine-MP.
 *
 * (c) Jorgebyte - AntiBugPing
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author  Jorgebyte
 * @link    https://discord.jorgebyte.com/
 * @license GNU General Public License v3.0
 */

namespace Jorgebyte\AntiBugPing\config;

use pocketmine\utils\Config;

use function array_key_exists;
use function is_array;
use function is_string;

final readonly class AntiBugPingSettings
{
	/**
	 * @param array<string, true> $excludedWorlds
	 */
	public function __construct(
		public string $profileName,
		public bool $enabled,
		public int $sampleSize,
		public int $minPingSamples,
		public int $minInputSamples,
		public int $highPingMs,
		public int $criticalPingMs,
		public int $highJitterMs,
		public int $silentTicksThreshold,
		public int $unstableMemoryTicks,
		public int $actionScoreCooldownTicks,
		public bool $watchdogEnabled,
		public int $watchdogIntervalTicks,
		public int $quarantineSilentMultiplier,
		public int $quarantineFreezeTicks,
		public float $scoreDecayPerTick,
		public float $restrictScore,
		public float $setbackScore,
		public float $freezeScore,
		public float $kickScore,
		public int $restrictTicks,
		public int $freezeTicks,
		public int $teleportGraceTicks,
		public bool $kickEnabled,
		public bool $notifyStaff,
		public bool $debug,
		public array $excludedWorlds
	) {
	}

	public static function fromConfig(Config $config): self
	{
		return self::fromArray($config->getAll());
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		$profileName = (string)self::read($data, 'profile', 'balanced');
		$enabled = (bool)self::read($data, 'enabled', true);
		$debug = (bool)self::read($data, 'debug', false);

		$sampleSize = self::clampInt(self::read($data, 'sample-size', 20), 6, 80);
		$minPingSamples = self::clampInt(self::read($data, 'min-ping-samples', 8), 3, $sampleSize);
		$minInputSamples = self::clampInt(self::read($data, 'min-input-samples', 8), 3, $sampleSize);

		$thresholds = self::readThresholds($data);
		$score = self::readScore($data);
		$mitigation = self::readMitigation($data);
		$watchdog = self::readWatchdog($data);
		$excludedWorlds = self::readExcludedWorlds($data);

		return new self(
			$profileName,
			$enabled,
			$sampleSize,
			$minPingSamples,
			$minInputSamples,
			$thresholds['highPingMs'],
			$thresholds['criticalPingMs'],
			$thresholds['highJitterMs'],
			$thresholds['silentTicksThreshold'],
			$thresholds['unstableMemoryTicks'],
			$thresholds['actionScoreCooldownTicks'],
			$watchdog['enabled'],
			$watchdog['intervalTicks'],
			$watchdog['quarantineSilentMultiplier'],
			$watchdog['quarantineFreezeTicks'],
			$score['decayPerTick'],
			$score['restrict'],
			$score['setback'],
			$score['freeze'],
			$score['kick'],
			$mitigation['restrictTicks'],
			$mitigation['freezeTicks'],
			$mitigation['teleportGraceTicks'],
			$mitigation['kickEnabled'],
			$mitigation['notifyStaff'],
			$debug,
			$excludedWorlds
		);
	}

	public function isWorldExcluded(string $worldName): bool
	{
		return isset($this->excludedWorlds[strtolower($worldName)]);
	}

	/**
	 * @return array{highPingMs:int,criticalPingMs:int,highJitterMs:int,silentTicksThreshold:int,unstableMemoryTicks:int,actionScoreCooldownTicks:int}
	 */
	private static function readThresholds(array $data): array
	{
		$highPingMs = self::clampInt(self::read($data, 'thresholds.high-ping-ms', 280), 80, 2_000);

		return [
			'highPingMs' => $highPingMs,
			'criticalPingMs' => self::clampInt(self::read($data, 'thresholds.critical-ping-ms', 500), $highPingMs + 20, 3_000),
			'highJitterMs' => self::clampInt(self::read($data, 'thresholds.high-jitter-ms', 130), 30, 1_000),
			'silentTicksThreshold' => self::clampInt(self::read($data, 'thresholds.silent-ticks', 16), 8, 80),
			'unstableMemoryTicks' => self::clampInt(self::read($data, 'thresholds.unstable-memory-ticks', 50), 10, 200),
			'actionScoreCooldownTicks' => self::clampInt(self::read($data, 'thresholds.action-score-cooldown-ticks', 12), 2, 80),
		];
	}

	/**
	 * @return array{decayPerTick:float,restrict:float,setback:float,freeze:float,kick:float}
	 */
	private static function readScore(array $data): array
	{
		$restrict = self::clampFloat(self::read($data, 'score.thresholds.restrict', 3.0), 0.5, 100.0);
		$setback = self::clampFloat(self::read($data, 'score.thresholds.setback', 5.5), $restrict + 0.5, 100.0);
		$freeze = self::clampFloat(self::read($data, 'score.thresholds.freeze', 7.0), $setback + 0.5, 100.0);

		return [
			'decayPerTick' => self::clampFloat(self::read($data, 'score.decay-per-tick', 0.12), 0.01, 4.0),
			'restrict' => $restrict,
			'setback' => $setback,
			'freeze' => $freeze,
			'kick' => self::clampFloat(self::read($data, 'score.thresholds.kick', 10.0), $freeze + 0.5, 150.0),
		];
	}

	/** @return array{restrictTicks:int,freezeTicks:int,teleportGraceTicks:int,kickEnabled:bool,notifyStaff:bool} */
	private static function readMitigation(array $data): array
	{
		return [
			'restrictTicks' => self::clampInt(self::read($data, 'mitigation.restrict-ticks', 30), 5, 200),
			'freezeTicks' => self::clampInt(self::read($data, 'mitigation.freeze-ticks', 20), 5, 200),
			'teleportGraceTicks' => self::clampInt(self::read($data, 'mitigation.teleport-grace-ticks', 40), 0, 300),
			'kickEnabled' => (bool)self::read($data, 'mitigation.kick-enabled', false),
			'notifyStaff' => (bool)self::read($data, 'mitigation.notify-staff', true),
		];
	}

	/** @return array{enabled:bool,intervalTicks:int,quarantineSilentMultiplier:int,quarantineFreezeTicks:int} */
	private static function readWatchdog(array $data): array
	{
		return [
			'enabled' => (bool)self::read($data, 'watchdog.enabled', true),
			'intervalTicks' => self::clampInt(self::read($data, 'watchdog.interval-ticks', 2), 1, 20),
			'quarantineSilentMultiplier' => self::clampInt(self::read($data, 'watchdog.quarantine-after-silent-multiplier', 2), 1, 6),
			'quarantineFreezeTicks' => self::clampInt(self::read($data, 'watchdog.quarantine-freeze-ticks', 12), 0, 80),
		];
	}

	/** @return array<string, true> */
	private static function readExcludedWorlds(array $data): array
	{
		$excludedWorlds = [];
		$rawWorlds = self::read($data, 'excluded-worlds', []);

		if (!is_array($rawWorlds)) {
			return $excludedWorlds;
		}

		foreach ($rawWorlds as $worldName) {
			if (is_string($worldName) && '' !== $worldName) {
				$excludedWorlds[strtolower($worldName)] = true;
			}
		}

		return $excludedWorlds;
	}

	private static function read(array $data, string $path, mixed $default): mixed
	{
		$segments = explode('.', $path);
		$current = $data;

		foreach ($segments as $segment) {
			if (!is_array($current) || !array_key_exists($segment, $current)) {
				return $default;
			}

			$current = $current[$segment];
		}

		return $current;
	}

	private static function clampInt(mixed $value, int $min, int $max): int
	{
		if (!is_numeric($value)) {
			return $min;
		}

		return max($min, min($max, (int)$value));
	}

	private static function clampFloat(mixed $value, float $min, float $max): float
	{
		if (!is_numeric($value)) {
			return $min;
		}

		return max($min, min($max, (float)$value));
	}
}
