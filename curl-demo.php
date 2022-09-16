<?php

use Minororange\AsyncSocket\Origin\MultiCurl;

require_once './vendor/autoload.php';


$startTime = microtime(true);
echo "start: {$startTime}\n";
$multiCurl = new MultiCurl([
    'http://localhost:8123?exec_time=1',
    'http://localhost:8123?exec_time=2',
    'http://localhost:8123?exec_time=3',
    'http://localhost:8123?exec_time=4',
    'http://localhost:8123?exec_time=5',
]);
$response = $multiCurl->request();
$spend = microtime(true) - $startTime;
echo "request complete in [$spend]s\n";

echo "responses:\n";

var_dump($response);