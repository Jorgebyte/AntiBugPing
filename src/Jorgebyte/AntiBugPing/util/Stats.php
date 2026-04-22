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

namespace Jorgebyte\AntiBugPing\util;

use function count;

final class Stats
{
	private const TICK_DURATION_MS = 50;

	/** @param list<int> $values */
	public static function median(array $values): int
	{
		$count = count($values);

		if (0 === $count) {
			return 0;
		}

		sort($values);
		$middle = intdiv($count, 2);

		if (($count % 2) === 0) {
			return (int)round(($values[$middle - 1] + $values[$middle]) / 2);
		}

		return $values[$middle];
	}

	/**
	 * Converts input interval variance (ticks) into an approximate jitter value in milliseconds.
	 *
	 * @param list<int> $intervalTicks
	 */
	public static function jitterMs(array $intervalTicks): int
	{
		$count = count($intervalTicks);

		if ($count < 2) {
			return 0;
		}

		$deltas = [];
		$previous = $intervalTicks[0];

		for ($i = 1; $i < $count; ++$i) {
			$current = $intervalTicks[$i];
			$deltas[] = abs($current - $previous) * self::TICK_DURATION_MS;
			$previous = $current;
		}

		return self::median($deltas);
	}
}
