<?php
/**
 * Cron definition attribute.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Marks a public method as a Blockstudio cron job.
 */
#[\Attribute( \Attribute::TARGET_METHOD )]
final readonly class Cron_Definition {

	/**
	 * Constructor.
	 *
	 * @param string|null               $name     Optional job name override.
	 * @param string|Cron_Schedule|null $schedule Schedule slug.
	 */
	public function __construct(
		public ?string $name = null,
		public string|Cron_Schedule|null $schedule = Cron_Schedule::Daily,
	) {
	}
}
