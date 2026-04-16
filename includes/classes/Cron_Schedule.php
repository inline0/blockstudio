<?php
/**
 * Backward compatibility alias for cron schedules.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

class_alias( \Blockstudio\Cron\Schedule::class, __NAMESPACE__ . '\Cron_Schedule' );
