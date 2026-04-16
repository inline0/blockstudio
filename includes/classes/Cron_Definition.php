<?php
/**
 * Backward compatibility alias for the cron attribute.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

class_alias( \Blockstudio\Attributes\Cron::class, __NAMESPACE__ . '\Cron_Definition' );
