<?php
/**
 * Cron attribute.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Api\Attributes;

use Blockstudio\Api\Cron\Schedule;

/**
 * Marks a public method as a Blockstudio cron job.
 */
#[\Attribute( \Attribute::TARGET_METHOD )]
final readonly class Cron {

	/**
	 * Constructor.
	 *
	 * @param string|null          $name     Optional job name override.
	 * @param string|Schedule|null $schedule Schedule slug.
	 */
	public function __construct(
		public ?string $name = null,
		public string|Schedule|null $schedule = Schedule::Daily,
	) {
	}
}
