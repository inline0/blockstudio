<?php
/**
 * Cron schedule enum.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Supported WordPress cron schedules.
 */
enum Cron_Schedule: string {
	case Hourly     = 'hourly';
	case TwiceDaily = 'twicedaily';
	case Daily      = 'daily';
	case Weekly     = 'weekly';
}
