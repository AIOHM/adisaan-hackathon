<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('tbwim:review-answers --limit=25')->everyTenMinutes()->withoutOverlapping();
Schedule::command('tbwim:synthesize --all --min-voices=10')->hourly()->withoutOverlapping();
Schedule::command('tbwim:battle-round')->everyTenMinutes()->withoutOverlapping();
Schedule::command('tbwim:transcribe-pending --limit=25')->dailyAt('06:25')->withoutOverlapping();
Schedule::command('tbwim:extract-pending --limit=25')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('tbwim:notify-pending --limit=25')->everyFiveMinutes()->withoutOverlapping();
// Tomorrow's question drafts itself every morning; the founder still
// approves or rejects every one, and a waiting draft blocks the next.
Schedule::command('tbwim:signal-question')->dailyAt('06:10')->timezone('Europe/Bucharest')->withoutOverlapping();
