<?php

use Minororange\AsyncSocket\Socket\Entity\ServerEntity;
use Minororange\AsyncSocket\Socket\FiberPool;
use Minororange\AsyncSocket\Socket\Http;
use Minororange\AsyncSocket\Socket\SocketClient;
use Minororange\AsyncSocket\Tools\Timer;

require_once './vendor/autoload.php';
$serverEntity = new ServerEntity();
$serverEntity->address = 'localhost';
$serverEntity->port = 8123;
$responses = [];
Timer::start("multi request");
for ($i = 1; $i <= 5; $i++) {

    $requestFiber = new \Fiber(function () use ($serverEntity, &$responses, $i) {
        $http = new Http($serverEntity, AF_INET, SOCK_STREAM, SOL_TCP);
        // 建立连接后，暂停当前，进入下一个循环
        FiberPool::getInstance()->enqueue("connect[{$i}]");
        Timer::start("request[{$i}]");
        $http->request(['exec_time' => $i]);
        // 发送数据后，暂停当前，进入下一个循环
        FiberPool::getInstance()->enqueue("send[{$i}]");
        $responses[] = $http->getResponse()->getBody();
        Timer::end("request[{$i}]");
    });
    $requestFiber->start();
}

FiberPool::getInstance()->run();

var_dump($responses);

Timer::end("multi request");