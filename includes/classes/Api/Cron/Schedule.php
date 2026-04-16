<?php
/**
 * Cron schedule enum.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Api\Cron;

/**
 * Supported WordPress cron schedules.
 */
enum Schedule: string {
	case Hourly     = 'hourly';
	case TwiceDaily = 'twicedaily';
	case Daily      = 'daily';
	case Weekly     = 'weekly';
}
