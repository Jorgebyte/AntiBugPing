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

namespace Jorgebyte\AntiBugPing\task;

use Jorgebyte\AntiBugPing\config\AntiBugPingSettings;
use Jorgebyte\AntiBugPing\detection\DetectionResult;
use Jorgebyte\AntiBugPing\mitigation\MitigationExecutor;
use Jorgebyte\AntiBugPing\mitigation\MitigationLevel;
use Jorgebyte\AntiBugPing\permission\Permission;
use Jorgebyte\AntiBugPing\session\SessionRepository;
use pocketmine\scheduler\Task;
use pocketmine\Server;

final class ConnectionWatchdogTask extends Task
{
	public function __construct(
		private readonly Server $server,
		private readonly AntiBugPingSettings $settings,
		private readonly SessionRepository $sessions,
		private readonly MitigationExecutor $mitigator
	) {
	}

	public function onRun(): void
	{
		if (!$this->settings->enabled || !$this->settings->watchdogEnabled) {
			return;
		}

		$silentLimit = $this->settings->silentTicksThreshold * $this->settings->quarantineSilentMultiplier;

		if ($silentLimit <= 0) {
			return;
		}

		$tick = $this->server->getTick();

		foreach ($this->server->getOnlinePlayers() as $player) {
			if ($player->hasPermission(Permission::BYPASS)) {
				continue;
			}

			if ($this->settings->isWorldExcluded($player->getWorld()->getFolderName())) {
				continue;
			}

			$state = $this->sessions->get($player);
			$silentTicks = $state->getSilentTicks($tick);

			if ($silentTicks < $silentLimit) {
				continue;
			}

			$state->markNetworkUnstableUntil($tick + $this->settings->unstableMemoryTicks);
			$state->setRestrictedUntil($tick + $this->settings->restrictTicks);

			$freezeTicks = min($this->settings->freezeTicks, $this->settings->quarantineFreezeTicks);

			if ($freezeTicks > 0) {
				$state->setFrozenUntil($tick + $freezeTicks);
			}

			$this->mitigator->notifyStaff(
				$player,
				new DetectionResult(
					$freezeTicks > 0 ? MitigationLevel::FREEZE : MitigationLevel::RESTRICT,
					$state->getScore(),
					$state->getLastMedianPingMs(),
					$state->getLastJitterMs(),
					'watchdog-silent-connection'
				),
				$tick
			);
		}
	}
}
