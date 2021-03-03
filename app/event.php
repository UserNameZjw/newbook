<?php
// 事件定义文件
return [
    'bind'      => [
    ],

    'listen'    => [
        'AppInit'  => [],
        'HttpRun'  => [],
        'HttpEnd'  => [],
        'LogLevel' => [],
        'LogWrite' => [],
        'swoole.task'   =>['\app\listener\SwooleTask'],
        // 'swoole.finish' =>['\app\listener\BookTaskFinish'],
        // init中无法使用swoole_timer_tick等函数
        'swoole.init' => ['\app\listener\SwooleBoot'],
        // managerStart中无法使用addProcess
        // 'swoole.managerStart' => ['\app\listener\SwooleBoot'],
    ],

    'subscribe' => [
            '\app\subscribe\Timer',
            '\app\subscribe\AutoDown',
        ],
];
