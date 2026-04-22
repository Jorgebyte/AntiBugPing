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

namespace Jorgebyte\AntiBugPing;

use Jorgebyte\AntiBugPing\config\AntiBugPingSettings;
use Jorgebyte\AntiBugPing\detection\DesyncDetector;
use Jorgebyte\AntiBugPing\listener\GameplayGuardListener;
use Jorgebyte\AntiBugPing\listener\NetworkPacketListener;
use Jorgebyte\AntiBugPing\mitigation\MitigationExecutor;
use Jorgebyte\AntiBugPing\session\SessionRepository;
use Jorgebyte\AntiBugPing\task\ConnectionWatchdogTask;
use pocketmine\plugin\PluginBase;

final class Main extends PluginBase
{
	private AntiBugPingSettings $settings;

	private SessionRepository $sessions;

	private DesyncDetector $detector;

	private MitigationExecutor $mitigator;

	protected function onEnable(): void
	{
		$this->saveDefaultConfig();

		$this->settings = AntiBugPingSettings::fromConfig($this->getConfig());
		$this->sessions = new SessionRepository();
		$this->detector = new DesyncDetector($this->settings);
		$this->mitigator = new MitigationExecutor(
			$this->getServer(),
			function (string $message): void {
				$this->getLogger()->debug($message);
			},
			$this->settings
		);

		$eventManager = $this->getServer()->getPluginManager();
		$eventManager->registerEvents(new NetworkPacketListener($this, $this->settings, $this->sessions, $this->detector, $this->mitigator), $this);
		$eventManager->registerEvents(new GameplayGuardListener($this, $this->settings, $this->sessions, $this->detector, $this->mitigator), $this);

		if ($this->settings->watchdogEnabled) {
			$this->getScheduler()->scheduleRepeatingTask(
				new ConnectionWatchdogTask($this->getServer(), $this->settings, $this->sessions, $this->mitigator),
				$this->settings->watchdogIntervalTicks
			);
		}
	}

	protected function onDisable(): void
	{
		$this->sessions->clear();
	}
}
