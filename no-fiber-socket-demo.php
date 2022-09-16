<?php

use Minororange\AsyncSocket\Socket\SocketClient as Client;
use Minororange\AsyncSocket\Socket\Entity\ServerEntity;
use Minororange\AsyncSocket\Socket\Http;
use Minororange\AsyncSocket\Tools\Timer;

require_once './vendor/autoload.php';
$serverEntity = new ServerEntity();
$serverEntity->address = 'localhost';
$serverEntity->port = 8123;
/** @var Http[] $httpClients */
$httpClients = [];
$responses = [];
Timer::start("multi request");
for ($i = 1; $i <= 5; $i++) {

    $http = new Http($serverEntity, AF_INET, SOCK_STREAM, SOL_TCP);
    $httpClients[$i] = $http;

    Timer::start("request[{$i}]");
    $http->request(['exec_time' => $i]);
}

foreach ($httpClients as $i => $httpClient) {
    $responses[] = $httpClient->getResponse()->getBody();
    $httpClient->close();
    Timer::end("request[{$i}]");
}

var_dump($responses);

Timer::end("multi request");