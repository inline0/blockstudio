<?php

namespace Blockstudio;

enum Cron_Schedule: string
{
    case Hourly = 'hourly';
    case TwiceDaily = 'twicedaily';
    case Daily = 'daily';
    case Weekly = 'weekly';
}
