<?php

use Illuminate\Support\Facades\Schedule;

// المُجدوِل يقرر فقط ما يُفحص الآن؛ التنفيذ في طوابير ingest/promote.
Schedule::command('datasets:dispatch')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('datasets:check-stale')->hourly();
Schedule::command('signals:check')->hourly()->withoutOverlapping();
Schedule::command('datasets:prune')->dailyAt('03:00');
Schedule::command('data:audit --quiet-ok')->dailyAt('06:00')->timezone(config('omanilab.display_timezone'));
Schedule::command('alerts:digest')->dailyAt(config('omanilab.alerts.digest_time'))->timezone(config('omanilab.display_timezone'));
Schedule::command('chains:validate')->weeklyOn(6, '05:00')->timezone(config('omanilab.display_timezone'))->withoutOverlapping();
Schedule::command('chains:trade')->weeklyOn(6, '04:00')->timezone(config('omanilab.display_timezone'))->withoutOverlapping();
Schedule::command('prices:watch')->dailyAt('06:30')->timezone(config('omanilab.display_timezone'))->withoutOverlapping();
Schedule::command('chains:forecast')->weeklyOn(6, '06:00')->timezone(config('omanilab.display_timezone'))->withoutOverlapping();
Schedule::command('insights:warm')->everyFiveMinutes()->withoutOverlapping();
