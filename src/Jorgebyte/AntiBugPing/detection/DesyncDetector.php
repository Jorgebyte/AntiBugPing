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

namespace Jorgebyte\AntiBugPing\detection;

use Jorgebyte\AntiBugPing\config\AntiBugPingSettings;
use Jorgebyte\AntiBugPing\mitigation\MitigationLevel;
use Jorgebyte\AntiBugPing\session\PlayerSessionState;
use Jorgebyte\AntiBugPing\util\Stats;
use pocketmine\player\Player;

use function count;

final readonly class DesyncDetector
{
	public function __construct(private AntiBugPingSettings $settings)
	{
	}

	public function onPacket(Player $player, PlayerSessionState $state, int $tick, bool $isInputPacket, bool $isActionPacket): DetectionResult
	{
		$state->applyDecay($tick, $this->settings->scoreDecayPerTick);

		$silentTicks = $state->markPacket($tick);
		$ping = $this->resolvePing($player);
		$state->pushPingSample($ping, $this->settings->sampleSize);

		if ($isInputPacket) {
			$interval = $state->markInputAndGetInterval($tick);

			if (null !== $interval) {
				$state->pushInputInterval($interval, $this->settings->sampleSize);
			}
		}

		$medianPing = Stats::median($state->getPingSamples());
		$jitter = Stats::jitterMs($state->getInputIntervalsTicks());
		$state->setNetworkStats($medianPing, $jitter);

		$hasEnoughPingSamples = count($state->getPingSamples()) >= $this->settings->minPingSamples;
		$hasEnoughInputSamples = count($state->getInputIntervalsTicks()) >= $this->settings->minInputSamples;

		$isHighPing = $hasEnoughPingSamples && $medianPing >= $this->settings->highPingMs;
		$isCriticalPing = $hasEnoughPingSamples && $medianPing >= $this->settings->criticalPingMs;
		$isHighJitter = $hasEnoughInputSamples && $jitter >= $this->settings->highJitterMs;
		$hadSilentBurst = $silentTicks >= $this->settings->silentTicksThreshold;

		if ($isHighPing || $isHighJitter || $hadSilentBurst) {
			$state->markNetworkUnstableUntil($tick + $this->settings->unstableMemoryTicks);
		}

		if ($isCriticalPing && $state->canAddScoreFor('packet-critical-ping', $tick, 10)) {
			$state->addScore(0.85);
		} elseif ($isHighPing && $state->canAddScoreFor('packet-high-ping', $tick, 14)) {
			$state->addScore(0.45);
		}

		if ($isHighJitter && $state->canAddScoreFor('packet-high-jitter', $tick, 12)) {
			$state->addScore(0.5);
		}

		if ($hadSilentBurst && $state->canAddScoreFor('packet-silent-burst', $tick, 16)) {
			$state->addScore(1.1);
		}

		if ($isActionPacket && $state->isNetworkUnstable($tick) && $state->canAddScoreFor('packet-action-while-unstable', $tick, 12)) {
			$state->addScore(0.35);
		}

		return new DetectionResult(
			$this->scoreToLevel($state->getScore()),
			$state->getScore(),
			$medianPing,
			$jitter,
			'packet-analysis'
		);
	}

	public function onSuspiciousAction(PlayerSessionState $state, int $tick, float $weight, string $reason): DetectionResult
	{
		$state->applyDecay($tick, $this->settings->scoreDecayPerTick);

		if ($state->isNetworkUnstable($tick) && $state->canAddScoreFor('action-' . $reason, $tick, $this->settings->actionScoreCooldownTicks)) {
			$state->addScore($weight);
		}

		return new DetectionResult(
			$this->scoreToLevel($state->getScore()),
			$state->getScore(),
			Stats::median($state->getPingSamples()),
			Stats::jitterMs($state->getInputIntervalsTicks()),
			$reason
		);
	}

	public function shouldScoreAction(PlayerSessionState $state, int $tick): bool
	{
		return $state->isNetworkUnstable($tick);
	}

	private function resolvePing(Player $player): int
	{
		$ping = $player->getNetworkSession()->getPing();

		return null !== $ping ? max(0, $ping) : 0;
	}

	private function scoreToLevel(float $score): MitigationLevel
	{
		if ($this->settings->kickEnabled && $score >= $this->settings->kickScore) {
			return MitigationLevel::KICK;
		}

		if ($score >= $this->settings->freezeScore) {
			return MitigationLevel::FREEZE;
		}

		if ($score >= $this->settings->setbackScore) {
			return MitigationLevel::SETBACK;
		}

		if ($score >= $this->settings->restrictScore) {
			return MitigationLevel::RESTRICT;
		}

		return MitigationLevel::NONE;
	}
}
