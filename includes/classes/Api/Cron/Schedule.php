<?php
/**
 * Backward compatibility alias for cron schedules.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Api\Cron;

class_alias( \Blockstudio\Cron\Schedule::class, __NAMESPACE__ . '\Schedule' );
