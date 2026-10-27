<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('tbwim:review-answers --limit=25')->everyTenMinutes()->withoutOverlapping();
Schedule::command('tbwim:synthesize')->hourly()->withoutOverlapping();
