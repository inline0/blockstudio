<?php

namespace Blockstudio;

/**
 * Block class.
 *
 * @date   24/02/2022
 * @since  2.2.0
 */
class Render
{
    /**
     * Render block function.
     *
     * @date   24/02/2022
     * @since  2.2.0
     *
     * @param  $value
     *
     * @return false|string|void
     */
    public static function block($value)
    {
        $data = [];
        $content = false;

        if (is_array($value)) {
            $name = $value['name'] ?? $value['id'];
            $data = $value['data'] ?? [];
            $content = $value['content'] ?? false;
        } else {
            $name = $value;
        }

        $blocks = Build::data();

        if (
            !isset($blocks[$name]['path']) &&
            !isset($data['_BLOCKSTUDIO_EDITOR_STRING'])
        ) {
            return false;
        }

        $editor = $data['_BLOCKSTUDIO_EDITOR_STRING'] ?? false;
        unset($data['_BLOCKSTUDIO_EDITOR_STRING']);

        if ($editor) {
            return Block::render([
                'blockstudio' => [
                    'editor' => $editor,
                    'name' => $name,
                    'attributes' => $data,
                ],
            ]);
        } else {
            echo Block::render(
                [
                    'blockstudio' => [
                        'name' => $name,
                        'attributes' => $data,
                    ],
                ],
                '',
                '',
                $content
            );
        }
    }
}
