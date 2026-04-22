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

namespace Jorgebyte\AntiBugPing\mitigation;

use Closure;
use Jorgebyte\AntiBugPing\config\AntiBugPingSettings;
use Jorgebyte\AntiBugPing\detection\DetectionResult;
use Jorgebyte\AntiBugPing\permission\Permission;
use Jorgebyte\AntiBugPing\session\PlayerSessionState;
use pocketmine\player\Player;
use pocketmine\Server;

final class MitigationExecutor
{
	/** @var array<string, int> */
	private array $lastNotifyTick = [];

	public function __construct(
		private readonly Server $server,
		private readonly Closure $debugLogger,
		private readonly AntiBugPingSettings $settings
	) {
	}

	public function apply(Player $player, PlayerSessionState $state, DetectionResult $result, int $tick): void
	{
		if ($this->shouldSkipMitigation($player)) {
			return;
		}

		$appliedLevel = $this->resolveAppliedLevel($result->level, $state, $tick);
		$appliedResult = $this->withAppliedLevel($result, $appliedLevel);

		switch ($appliedLevel) {
			case MitigationLevel::NONE:
				break;

			case MitigationLevel::RESTRICT:
				$state->setRestrictedUntil($tick + $this->settings->restrictTicks);
				break;

			case MitigationLevel::SETBACK:
				$state->setRestrictedUntil($tick + $this->settings->restrictTicks);
				$this->teleportToLastSafePosition($player, $state, $tick);
				break;

			case MitigationLevel::FREEZE:
				$state->setRestrictedUntil($tick + $this->settings->restrictTicks);
				$state->setFrozenUntil($tick + $this->settings->freezeTicks);
				$this->teleportToLastSafePosition($player, $state, $tick);
				break;

			case MitigationLevel::KICK:
				$player->kick('Connection desync detected, please reconnect with a stable network');
				break;
		}

		if (MitigationLevel::NONE !== $appliedLevel) {
			$this->notifyStaff($player, $appliedResult, $tick);
		}
	}

	public function notifyStaff(Player $flagged, DetectionResult $result, int $tick): void
	{
		if (!$this->settings->notifyStaff) {
			return;
		}

		$playerName = strtolower($flagged->getName());
		$lastTick = $this->lastNotifyTick[$playerName] ?? 0;

		if (($tick - $lastTick) < 20) {
			return;
		}
		$this->lastNotifyTick[$playerName] = $tick;

		$message = $this->buildStaffMessage($flagged, $result);

		foreach ($this->server->getOnlinePlayers() as $online) {
			if ($online->hasPermission(Permission::NOTIFY)) {
				$online->sendMessage($message);
			}
		}

		if ($this->settings->debug) {
			($this->debugLogger)($message);
		}
	}

	private function teleportToLastSafePosition(Player $player, PlayerSessionState $state, int $tick): void
	{
		$safe = $state->getLastSafePosition();

		if (null === $safe) {
			return;
		}

		$player->teleport($safe);
		$state->setGraceUntil($tick + $this->settings->teleportGraceTicks);
	}

	private function shouldSkipMitigation(Player $player): bool
	{
		if (!$this->settings->enabled || !$player->isConnected()) {
			return true;
		}

		return $player->hasPermission(Permission::BYPASS);
	}

	private function resolveAppliedLevel(MitigationLevel $detectedLevel, PlayerSessionState $state, int $tick): MitigationLevel
	{
		if (MitigationLevel::FREEZE === $detectedLevel && !$state->isNetworkUnstable($tick)) {
			// Do not freeze unless network instability is currently confirmed
			return MitigationLevel::SETBACK;
		}

		return $detectedLevel;
	}

	private function withAppliedLevel(DetectionResult $result, MitigationLevel $appliedLevel): DetectionResult
	{
		if ($result->level === $appliedLevel) {
			return $result;
		}

		return new DetectionResult(
			$appliedLevel,
			$result->score,
			$result->medianPingMs,
			$result->jitterMs,
			$result->reason
		);
	}

	private function buildStaffMessage(Player $flagged, DetectionResult $result): string
	{
		$score = number_format($result->score, 2, '.', '');

		return '[AntiBugPing] '
			. $flagged->getName()
			. ' level=' . $result->level->name
			. ' score=' . $score
			. ' ping=' . $result->medianPingMs . 'ms'
			. ' jitter=' . $result->jitterMs . 'ms'
			. ' reason=' . $result->reason;
	}
}
