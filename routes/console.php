<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('cronshim:evaluate-health')->everyMinute()->withoutOverlapping();
Schedule::command('cronshim:prune')->dailyAt('03:15')->withoutOverlapping();
