<?php

use Blockstudio\Settings;

Settings::get('tailwind/enabled');
Settings::get('users/ids');
Settings::get('assets/enqueue');
Settings::get('blockTags/allow');
Settings::get('dev/perf');
Settings::get_string('performance/profile');
Settings::get_bool('performance/media/lazy');
Settings::get_int('performance/staticPrerender/ttl');
Settings::get_array('performance/staticPrerender/dynamicPaths');
