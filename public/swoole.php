<?php

require __DIR__ . '/../vendor/autoload.php';

// 执行HTTP应用并响应
$app = (new think\App())->http;

//高性能HTTP服务器
$http = new Swoole\Http\Server("0.0.0.0", 9501);
$http->on("request", function ($request_swoole, $response_swoole) use ($app) {
    $response = $app->run();
    $response_swoole->header("Content-Type", "text/html;charset=utf-8");
    $response_swoole->end( $response->getData());
    $app->end($response);
});

$http->start();