<?php

add_filter('blockstudio/settings/tailwind/enabled', '__return_true');
add_filter('blockstudio/settings/users/ids', function ($ids) { return $ids; });
