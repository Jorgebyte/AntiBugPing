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

use Closure;
use Jorgebyte\AntiBugPing\config\AntiBugPingSettings;
use Jorgebyte\AntiBugPing\detection\DesyncDetector;
use Jorgebyte\AntiBugPing\Main;
use Jorgebyte\AntiBugPing\mitigation\MitigationExecutor;
use Jorgebyte\AntiBugPing\permission\Permission;
use Jorgebyte\AntiBugPing\session\PlayerSessionState;
use Jorgebyte\AntiBugPing\session\SessionRepository;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\player\Player;

final readonly class GameplayGuardListener implements Listener
{
	public function __construct(
		private Main $plugin,
		private AntiBugPingSettings $settings,
		private SessionRepository $sessions,
		private DesyncDetector $detector,
		private MitigationExecutor $mitigator
	) {
	}

	public function onJoin(PlayerJoinEvent $event): void
	{
		$tick = $this->plugin->getServer()->getTick();
		$state = $this->sessions->get($event->getPlayer());
		$state->setGraceUntil($tick + $this->settings->teleportGraceTicks);
	}

	public function onTeleport(EntityTeleportEvent $event): void
	{
		$entity = $event->getEntity();

		if (!$entity instanceof Player) {
			return;
		}

		$tick = $this->plugin->getServer()->getTick();
		$state = $this->sessions->get($entity);
		$state->setGraceUntil($tick + $this->settings->teleportGraceTicks);
	}

	public function onMove(PlayerMoveEvent $event): void
	{
		$player = $event->getPlayer();

		if ($this->shouldSkipPlayerChecks($player)) {
			return;
		}

		$tick = $this->plugin->getServer()->getTick();
		$state = $this->sessions->get($player);

		if ($this->blockMoveIfFrozen($event, $state, $tick)) {
			return;
		}

		$this->rememberSafePosition($player, $state, $event, $tick);
		$this->evaluateAbnormalMoveStep($player, $state, $event, $tick);
	}

	public function onBreak(BlockBreakEvent $event): void
	{
		$this->evaluateGameplayAction(
			$event->getPlayer(),
			'block-break',
			1.3,
			static fn () => $event->cancel()
		);
	}

	public function onPlace(BlockPlaceEvent $event): void
	{
		$this->evaluateGameplayAction(
			$event->getPlayer(),
			'block-place',
			1.3,
			static fn () => $event->cancel()
		);
	}

	public function onInteract(PlayerInteractEvent $event): void
	{
		$this->evaluateGameplayAction(
			$event->getPlayer(),
			'player-interact',
			1.1,
			static fn () => $event->cancel()
		);
	}

	public function onDamage(EntityDamageByEntityEvent $event): void
	{
		$damager = $event->getDamager();

		if (!$damager instanceof Player) {
			return;
		}

		$this->evaluateGameplayAction(
			$damager,
			'entity-attack',
			1.0,
			static fn () => $event->cancel()
		);
	}

	/** @param Closure():void $onBlocked */
	private function evaluateGameplayAction(Player $player, string $reason, float $weight, Closure $onBlocked): void
	{
		if ($this->shouldSkipPlayerChecks($player)) {
			return;
		}

		$tick = $this->plugin->getServer()->getTick();
		$state = $this->sessions->get($player);

		if ($this->isActionCurrentlyBlocked($state, $tick)) {
			$onBlocked();

			return;
		}

		if (!$this->detector->shouldScoreAction($state, $tick)) {
			return;
		}

		$result = $this->detector->onSuspiciousAction($state, $tick, $weight, $reason);
		$this->mitigator->apply($player, $state, $result, $tick);

		if ($state->isRestricted($tick) || $state->isFrozen($tick)) {
			$onBlocked();
		}
	}

	private function shouldSkipPlayerChecks(Player $player): bool
	{
		if (!$this->settings->enabled) {
			return true;
		}

		if ($player->hasPermission(Permission::BYPASS)) {
			return true;
		}

		return $this->settings->isWorldExcluded($player->getWorld()->getFolderName());
	}

	private function isActionCurrentlyBlocked(PlayerSessionState $state, int $tick): bool
	{
		return $state->isRestricted($tick) || $state->isFrozen($tick);
	}

	private function blockMoveIfFrozen(PlayerMoveEvent $event, PlayerSessionState $state, int $tick): bool
	{
		if (!$state->isFrozen($tick)) {
			return false;
		}

		$event->cancel();
		$event->setTo($event->getFrom());

		return true;
	}

	private function rememberSafePosition(Player $player, PlayerSessionState $state, PlayerMoveEvent $event, int $tick): void
	{
		if ($state->inGrace($tick)) {
			return;
		}

		// keep only stable ground positions as rollback anchors
		if ($player->isOnGround() && !$player->isSleeping()) {
			$state->setLastSafePosition($event->getTo());
		}
	}

	private function evaluateAbnormalMoveStep(Player $player, PlayerSessionState $state, PlayerMoveEvent $event, int $tick): void
	{
		if ($state->inGrace($tick) || !$this->detector->shouldScoreAction($state, $tick)) {
			return;
		}

		$distanceSquared = $event->getFrom()->distanceSquared($event->getTo());

		if ($distanceSquared <= 16.0) {
			return;
		}

		$result = $this->detector->onSuspiciousAction($state, $tick, 1.6, 'abnormal-move-step');
		$this->mitigator->apply($player, $state, $result, $tick);
	}
}
