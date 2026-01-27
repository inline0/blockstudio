<?php

namespace Blockstudio;

/**
 * Populate class.
 *
 * @date   21/08/2022
 * @since  2.6.0
 */
class Populate
{
    /**
     * Initialise population for attributes.
     *
     * @date   20/09/2022
     * @since  3.0.4
     *
     * @param  $data
     * @param  bool  $extraIds
     *
     * @return array
     */
    public static function init($data, $extraIds = false): array
    {
        $query = [];
        $arguments = $data['arguments'] ?? [];
        $custom =
            count(
                apply_filters('blockstudio/blocks/attributes/populate', [])
            ) >= 1
                ? apply_filters('blockstudio/blocks/attributes/populate', [])
                : apply_filters('blockstudio/blocks/populate', []);

        if ($data['type'] === 'custom' && isset($custom[$data['custom']])) {
            $query = $custom[$data['custom']];
        }

        if (
            $data['type'] === 'function' &&
            isset($data['function']) &&
            function_exists($data['function'])
        ) {
            $query = (array) call_user_func_array(
                $data['function'],
                $data['arguments'] ?? []
            );
        }

        if (!isset($data['query'])) {
            return $query;
        }

        if ($data['query'] === 'posts') {
            $originalPosts = get_posts($arguments);

            if ($extraIds) {
                $extraIds = is_array($extraIds) ? $extraIds : [$extraIds];
                $extraPostsArgs = [
                    'include' => $extraIds,
                    'postType' => 'any',
                    'postStatus' => 'any',
                ];

                $extraPosts = get_posts($extraPostsArgs);

                $originalPosts = array_unique(
                    array_merge($originalPosts, $extraPosts),
                    SORT_REGULAR
                );
            }

            $query = $originalPosts;
        }

        if ($data['query'] === 'users') {
            $originalUsers = get_users($arguments);

            if ($extraIds) {
                $extraIds = is_array($extraIds) ? $extraIds : [$extraIds];
                $extraUsersArgs = [
                    'include' => $extraIds,
                ];

                $extraUsers = get_users($extraUsersArgs);

                $originalUsers = array_unique(
                    array_merge($originalUsers, $extraUsers),
                    SORT_REGULAR
                );
            }

            $query = $originalUsers;
        }

        if ($data['query'] === 'terms') {
            $originalTerms = get_terms($arguments);

            if (is_wp_error($originalTerms)) {
                $originalTerms = [];
            }

            if ($extraIds) {
                $extraIds = is_array($extraIds) ? $extraIds : [$extraIds];
                $extraTermsArgs = [
                    'include' => $extraIds,
                ];

                $extraTerms = get_terms($extraTermsArgs);

                if (is_wp_error($extraTerms)) {
                    $extraTerms = [];
                }

                $originalTerms = array_unique(
                    array_merge($originalTerms, $extraTerms),
                    SORT_REGULAR
                );
            }

            $query = $originalTerms;
        }

        return $query;
    }
}
