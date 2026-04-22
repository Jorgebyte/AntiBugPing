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

namespace Jorgebyte\AntiBugPing\session;

use pocketmine\world\Position;

use function count;

use const PHP_INT_MIN;

final class PlayerSessionState
{
	/** @var list<int> */
	private array $pingSamples = [];

	/** @var list<int> */
	private array $inputIntervalsTicks = [];

	private int $lastScoreUpdateTick = 0;

	private int $lastPacketTick = 0;

	private int $lastInputTick = 0;

	private float $score = 0.0;

	private int $restrictedUntilTick = 0;

	private int $frozenUntilTick = 0;

	private int $graceUntilTick = 0;

	private ?Position $lastSafePosition = null;

	private int $unstableUntilTick = 0;

	private int $lastMedianPingMs = 0;

	private int $lastJitterMs = 0;

	/** @var array<string, int> */
	private array $lastReasonScoreTick = [];

	public function applyDecay(int $currentTick, float $decayPerTick): void
	{
		if (0 === $this->lastScoreUpdateTick) {
			$this->lastScoreUpdateTick = $currentTick;

			return;
		}

		$elapsedTicks = $currentTick - $this->lastScoreUpdateTick;

		if ($elapsedTicks > 0) {
			$this->score = max(0.0, $this->score - ($elapsedTicks * $decayPerTick));
			$this->lastScoreUpdateTick = $currentTick;
		}
	}

	public function markPacket(int $currentTick): int
	{
		$silenceTicks = $this->lastPacketTick > 0 ? ($currentTick - $this->lastPacketTick) : 0;
		$this->lastPacketTick = $currentTick;

		return $silenceTicks;
	}

	public function getSilentTicks(int $currentTick): int
	{
		if (0 === $this->lastPacketTick) {
			return 0;
		}

		return max(0, $currentTick - $this->lastPacketTick);
	}

	public function markInputAndGetInterval(int $currentTick): ?int
	{
		if (0 === $this->lastInputTick) {
			$this->lastInputTick = $currentTick;

			return null;
		}

		$interval = max(1, $currentTick - $this->lastInputTick);
		$this->lastInputTick = $currentTick;

		return $interval;
	}

	public function addScore(float $amount): void
	{
		if ($amount <= 0.0) {
			return;
		}

		$this->score += $amount;
	}

	public function getScore(): float
	{
		return $this->score;
	}

	public function pushPingSample(int $pingMs, int $maxSamples): void
	{
		$this->pingSamples[] = max(0, $pingMs);

		if (count($this->pingSamples) > $maxSamples) {
			array_shift($this->pingSamples);
		}
	}

	public function pushInputInterval(int $intervalTicks, int $maxSamples): void
	{
		$this->inputIntervalsTicks[] = max(1, $intervalTicks);

		if (count($this->inputIntervalsTicks) > $maxSamples) {
			array_shift($this->inputIntervalsTicks);
		}
	}

	/** @return list<int> */
	public function getPingSamples(): array
	{
		return $this->pingSamples;
	}

	/** @return list<int> */
	public function getInputIntervalsTicks(): array
	{
		return $this->inputIntervalsTicks;
	}

	public function isRestricted(int $tick): bool
	{
		return $tick <= $this->restrictedUntilTick;
	}

	public function isFrozen(int $tick): bool
	{
		return $tick <= $this->frozenUntilTick;
	}

	public function setRestrictedUntil(int $tick): void
	{
		$this->restrictedUntilTick = max($this->restrictedUntilTick, $tick);
	}

	public function setFrozenUntil(int $tick): void
	{
		$this->frozenUntilTick = max($this->frozenUntilTick, $tick);
	}

	public function setGraceUntil(int $tick): void
	{
		$this->graceUntilTick = max($this->graceUntilTick, $tick);
	}

	public function inGrace(int $tick): bool
	{
		return $tick <= $this->graceUntilTick;
	}

	public function setLastSafePosition(Position $position): void
	{
		$this->lastSafePosition = $position;
	}

	public function getLastSafePosition(): ?Position
	{
		return $this->lastSafePosition;
	}

	public function setNetworkStats(int $medianPingMs, int $jitterMs): void
	{
		$this->lastMedianPingMs = max(0, $medianPingMs);
		$this->lastJitterMs = max(0, $jitterMs);
	}

	public function getLastMedianPingMs(): int
	{
		return $this->lastMedianPingMs;
	}

	public function getLastJitterMs(): int
	{
		return $this->lastJitterMs;
	}

	public function markNetworkUnstableUntil(int $tick): void
	{
		$this->unstableUntilTick = max($this->unstableUntilTick, $tick);
	}

	public function isNetworkUnstable(int $tick): bool
	{
		return $tick <= $this->unstableUntilTick;
	}

	public function canAddScoreFor(string $reason, int $tick, int $cooldownTicks): bool
	{
		$lastTick = $this->lastReasonScoreTick[$reason] ?? PHP_INT_MIN;

		if (($tick - $lastTick) < $cooldownTicks) {
			return false;
		}

		$this->lastReasonScoreTick[$reason] = $tick;

		return true;
	}
}
