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

use Jorgebyte\AntiBugPing\mitigation\MitigationLevel;

final readonly class DetectionResult
{
	public function __construct(
		public MitigationLevel $level,
		public float $score,
		public int $medianPingMs,
		public int $jitterMs,
		public string $reason
	) {
	}
}
