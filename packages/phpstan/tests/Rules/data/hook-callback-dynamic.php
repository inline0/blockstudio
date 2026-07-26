<?php

add_filter('blockstudio/performance/staticPrerender/enabled', '__return_true');
add_filter('blockstudio/performance/media/lazy', '__return_true');
add_filter('blockstudio/settings/performance/media/lazy', '__return_true');
add_filter('blockstudio/ui/button/variants-style', function ($css) { return $css; });
