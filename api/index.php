<?php

require __DIR__ . '/bootstrap.php';

// Laravel's router expects the script to look like public/index.php.
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require __DIR__ . '/../public/index.php';
