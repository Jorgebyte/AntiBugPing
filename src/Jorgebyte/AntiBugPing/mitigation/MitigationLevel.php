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

enum MitigationLevel: int
{
	case NONE = 0;
	case RESTRICT = 1;
	case SETBACK = 2;
	case FREEZE = 3;
	case KICK = 4;
}
