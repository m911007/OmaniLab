<?php

use Illuminate\Support\Facades\Schedule;

// المُجدوِل يقرر فقط ما يُفحص الآن؛ التنفيذ في طوابير ingest/promote.
Schedule::command('datasets:dispatch')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('datasets:check-stale')->hourly();
Schedule::command('datasets:prune')->dailyAt('03:00');
Schedule::command('data:audit --quiet-ok')->dailyAt('06:00')->timezone(config('omanilab.display_timezone'));
Schedule::command('alerts:digest')->dailyAt(config('omanilab.alerts.digest_time'))->timezone(config('omanilab.display_timezone'));
