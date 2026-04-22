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

use pocketmine\player\Player;
use WeakMap;

final class SessionRepository
{
	/** @var WeakMap<Player, PlayerSessionState> */
	private WeakMap $states;

	public function __construct()
	{
		$this->states = new WeakMap();
	}

	public function get(Player $player): PlayerSessionState
	{
		return $this->states[$player] ??= new PlayerSessionState();
	}

	public function clear(): void
	{
		$this->states = new WeakMap();
	}
}
