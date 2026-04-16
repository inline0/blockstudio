<?php

namespace Blockstudio\Api\Cron;

enum Schedule: string
{
    case Hourly = 'hourly';
    case TwiceDaily = 'twicedaily';
    case Daily = 'daily';
    case Weekly = 'weekly';
}
