<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('observatory:review-answers --limit=25')->everyTenMinutes()->withoutOverlapping();
Schedule::command('observatory:synthesize')->hourly()->withoutOverlapping();
