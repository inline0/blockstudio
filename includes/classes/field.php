<?php

namespace Blockstudio;

/**
 * Field class.
 *
 * @date   21/08/2022
 * @since  2.6.0
 */
class Field
{
    /**
     * Get group.
     *
     * @date   21/08/2022
     * @since  2.6.0
     *
     * @param  $attributes
     * @param  $group
     *
     * @return array
     */
    public static function group($attributes, $group): array
    {
        $g = [];
        foreach ($attributes as $k => $v) {
            if (strpos($k, $group) === 0) {
                $len = strlen($group . '_');
                $key = substr($k, $len);
                $g[$key] = $v;
            }
        }

        return $g;
    }
}
