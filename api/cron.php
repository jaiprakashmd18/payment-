<?php

/*
 * Replaces the `* * * * * php artisan schedule:run` crontab and the queue
 * worker that Paymenter normally runs alongside the web server.
 *
 * Call it with `Authorization: Bearer <CRON_SECRET>` (Vercel Cron does this
 * automatically). Pass ?daily=1 to force the daily billing job, which is what
 * the once-a-day Vercel Cron entry needs on the Hobby plan.
 */

require __DIR__ . '/bootstrap.php';

$secret = getenv('CRON_SECRET');
if (!$secret || !hash_equals('Bearer ' . $secret, $_SERVER['HTTP_AUTHORIZATION'] ?? '')) {
    http_response_code(401);
    exit('Unauthorized');
}

// schedule:run starts each task as `php artisan ...` relative to the working directory.
chdir(__DIR__ . '/..');

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

header('Content-Type: text/plain; charset=utf-8');

$commands = [['schedule:run', []]];
// Vercel Cron on Hobby fires once a day at an unpredictable minute, so the
// daily job would never match its scheduled time. Run it directly instead.
// Skip this when an external every-minute scheduler is set up, because
// schedule:run already runs the daily job at its configured time then.
if (isset($_GET['daily']) && getenv('PAYMENTER_EXTERNAL_SCHEDULER') !== 'true') {
    $commands[] = ['app:cron-job', []];
}
$commands[] = ['queue:work', ['--stop-when-empty' => true, '--max-time' => 40, '--tries' => 3]];

foreach ($commands as [$command, $arguments]) {
    $output = new Symfony\Component\Console\Output\BufferedOutput;
    $status = $kernel->call($command, $arguments, $output);
    echo "> {$command} (exit {$status})\n" . $output->fetch() . "\n";
}

$kernel->terminate(new Symfony\Component\Console\Input\ArrayInput([]), 0);
