<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('tbwim:review-answers --limit=25')->everyTenMinutes()->withoutOverlapping();
Schedule::command('tbwim:synthesize')->hourly()->withoutOverlapping();
Schedule::command('tbwim:battle-round')->dailyAt('06:10')->withoutOverlapping();
Schedule::command('tbwim:transcribe-pending --limit=25')->dailyAt('06:25')->withoutOverlapping();
