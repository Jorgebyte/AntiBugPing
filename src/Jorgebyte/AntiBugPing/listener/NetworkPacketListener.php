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

namespace Jorgebyte\AntiBugPing\listener;

use Jorgebyte\AntiBugPing\config\AntiBugPingSettings;
use Jorgebyte\AntiBugPing\detection\DesyncDetector;
use Jorgebyte\AntiBugPing\Main;
use Jorgebyte\AntiBugPing\mitigation\MitigationExecutor;
use Jorgebyte\AntiBugPing\permission\Permission;
use Jorgebyte\AntiBugPing\session\SessionRepository;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;

final readonly class NetworkPacketListener implements Listener
{
	public function __construct(
		private Main $plugin,
		private AntiBugPingSettings $settings,
		private SessionRepository $sessions,
		private DesyncDetector $detector,
		private MitigationExecutor $mitigator
	) {
	}

	public function onDataPacketReceive(DataPacketReceiveEvent $event): void
	{
		if (!$this->settings->enabled) {
			return;
		}

		$player = $event->getOrigin()->getPlayer();

		if (null === $player || !$player->isConnected() || $player->hasPermission(Permission::BYPASS)) {
			return;
		}

		if ($this->settings->isWorldExcluded($player->getWorld()->getFolderName())) {
			return;
		}

		$packet = $event->getPacket();
		$isInput = $packet instanceof PlayerAuthInputPacket;
		$isAction = $packet instanceof PlayerActionPacket || $packet instanceof InventoryTransactionPacket;

		if (!$isInput && !$isAction) {
			return;
		}

		$tick = $this->plugin->getServer()->getTick();
		$state = $this->sessions->get($player);

		$result = $this->detector->onPacket($player, $state, $tick, $isInput, $isAction);
		$this->mitigator->apply($player, $state, $result, $tick);
	}
}
