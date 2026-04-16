<?php
/**
 * Backward compatibility alias for the public cron schedule API.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

class_alias( \Blockstudio\Api\Cron\Schedule::class, __NAMESPACE__ . '\Cron_Schedule' );
