### 前言

PHP 中的代码执行都是从上到下的（同步），在需要请求第三方 API 时，通常使用的库都是同步且阻塞的，如果需要同时请求 API1 和 API2，那么脚本的耗时就是 API1 + API2，在查找了相关资料后发现 PHP 对于批量的
cURL 有底层函数支持 [`curl_multi_exec`](`curl_multi_exec`)，使用这个函数之后最终的脚本耗时为 max(API1,API2).

但是由于 `curl_multi_exec` 的非阻塞调度都在 PHP 源码中，无法直观的感受非阻塞调度的流程，所以进一步研究了 `socket_select` 相关，同时结合 PHP8.1 中的 Fiber 写了几个
Demo，加深非阻塞、阻塞、异步、同步的理解。

### 模拟 API

首先创建一个耗时的 API 服务：

index.php

```php
$execTime = $_GET['exec_time'] ?? 1;

sleep((int)$execTime);

echo "exec complete in {$execTime}s\n";
```

通过 GET 参数来决定这个服务耗时

> 服务需要部署到 nginx 中，`php -S` 生成的服务器不支持并发，会影响 Demo 的结果，nginx.conf 参考 ./async.conf

### curl_multi_exec

curl-demo.php

```php
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
```

> curl_multi_exec 函数相关其他操作比较多，所以封装了 `MultiCurl`，具体用法可查看该类源码

```bash
$ php curl-demo.php

# 以下是输出结果
start: 1663316982.7557
curl start at:[1663316982.7806]
curl complete in [6.0205311775208]s
curl start at:[1663316982.7807]
curl complete in [6.0203881263733]s
curl start at:[1663316982.7808]
curl complete in [6.0203499794006]s
curl start at:[1663316982.7809]
curl complete in [6.0202620029449]s
curl start at:[1663316982.781]
curl complete in [6.0201759338379]s
request complete in [6.0454359054565]s
responses:
array(5) {
  [0]=>
  string(20) "exec complete in 1s"
  [1]=>
  string(20) "exec complete in 2s"
  [2]=>
  string(20) "exec complete in 3s"
  [3]=>
  string(20) "exec complete in 4s"
  [4]=>
  string(20) "exec complete in 5s"
}

```

API 中最大耗时为 5s，5 个 API 如果串行，总耗时应该是 1+2+3+4+5 = 15s ，使用 curl_multi_exec 最终耗时 6s 左右


### Socket

PHP 中的 socket 相关函数可参阅：https://www.php.net/manual/zh/ref.sockets.php

本项目主要使用以下几个函数：

- socket_create: 创建一个socket
- socket_set_nonblock:将 socket 设置为非阻塞
- socket_connect:连接
- socket_select:调用系统的 select 方法（结合非阻塞 IO 使用，NIO、BIO和 Selector的介绍和参阅：https://learnku.com/articles/65347）
- socket_write:写入数据（发送数据）
- socket_read:读取数据

no-fiber-socket-demo.php

```php
$serverEntity = new ServerEntity();
$serverEntity->address = 'localhost';
$serverEntity->port = 8123;
/** @var Http[] $httpClients */
$httpClients = [];
$responses = [];
Timer::start("multi request");
for ($i = 1; $i <= 5; $i++) {
    $http = new Http(new Client($serverEntity, AF_INET, SOCK_STREAM, SOL_TCP));
    $httpClients[$i] = $http;
    Timer::start("request[{$i}]");
    $http->request(['exec_time' => $i]);
}

foreach ($httpClients as $i => $httpClient) {
    $responses[] = $httpClient->getResponse()->getBody();
    $httpClient->getClient()->close();
    Timer::end("request[{$i}]");
}

var_dump($responses);

Timer::end("multi request");
```

```bash
$ php no-fiber-socket-demo.php

# 以下是输出结果
multi request started,began time:[1663317900.7515]s
request[1] started,began time:[1663317900.7627]s
request[2] started,began time:[1663317900.7628]s
request[3] started,began time:[1663317900.7628]s
request[4] started,began time:[1663317900.7629]s
request[5] started,began time:[1663317900.763]s
request[1] completed,time usage:[1.018]s
request[2] completed,time usage:[2.7019]s
request[3] completed,time usage:[4.0148]s
request[4] completed,time usage:[4.015]s
request[5] completed,time usage:[5.0061]s
array(5) {
  [0]=>
  string(19) "exec complete in 1s"
  [1]=>
  string(19) "exec complete in 2s"
  [2]=>
  string(19) "exec complete in 3s"
  [3]=>
  string(19) "exec complete in 4s"
  [4]=>
  string(19) "exec complete in 5s"
}
multi request completed,time usage:[5.0177]s
```

运行结果与 curl_multi_exec 差不多

- Client 请求时序图：

![Client 请求时序图](https://cdn.learnku.com/uploads/images/202209/16/24372/NqKtBKfCoi.png!large)

- 批量请求代码流程图：

![Laravel](https://cdn.learnku.com/uploads/images/202209/16/24372/AKSlgSAh2V.png!large)


### Socket 和 Fiber

Fiber 相关文档参阅：https://www.php.net/manual/zh/class.fiber.php

此项目主要使用的 Fiber 方法：

- new Fiber: 新建一个纤程
- Fiber::start: 启动纤程
- Fiber::suspend:暂停纤程
- Fiber::resume:恢复Fiber执行
- Fiber::getCurrent:获取当前Fiber实例

fiber-socket-demo.php
```php
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
```

Demo 代码与 Socket 的代码大致相同，只是中间加入了 Fiber 的暂停功能，代码流程图如下：

![Laravel](https://cdn.learnku.com/uploads/images/202209/16/24372/f6APua4Rq4.png!large)


运行结果：

```text
$ php fiber-socket-demo.php

# 以下是运行结果
multi request started,began time:[1663324710.2827]s
connect[1] await
connect[2] await
connect[3] await
connect[4] await
connect[5] await
connect[1] resume
request[1] started,began time:[1663324710.3021]s
send[1] await
connect[2] resume
request[2] started,began time:[1663324710.3023]s
send[2] await
connect[3] resume
request[3] started,began time:[1663324710.3023]s
send[3] await
connect[4] resume
request[4] started,began time:[1663324710.3023]s
send[4] await
connect[5] resume
request[5] started,began time:[1663324710.3023]s
send[5] await
send[1] resume
request[1] completed,time usage:[1.0376]s
send[2] resume
request[2] completed,time usage:[2.0132]s
send[3] resume
request[3] completed,time usage:[3.0138]s
send[4] resume
request[4] completed,time usage:[4.8346]s
send[5] resume
request[5] completed,time usage:[6.026]s
array(5) {
  [0]=>
  string(19) "exec complete in 1s"
  [1]=>
  string(19) "exec complete in 2s"
  [2]=>
  string(19) "exec complete in 3s"
  [3]=>
  string(19) "exec complete in 4s"
  [4]=>
  string(19) "exec complete in 5s"
}
multi request completed,time usage:[6.0459]s
```