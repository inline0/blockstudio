<?php
/**
 * Backward compatibility alias for the public cron attribute API.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

class_alias( \Blockstudio\Api\Attributes\Cron::class, __NAMESPACE__ . '\Cron_Definition' );
