<?php 
return [
    // 以事件类名为区分 当前定义均在 app\subscribe 目录下
    // 新章定时配置 
    'Timer'     => [
        'tally' => 1000,
        'rows'  => 10
    ],

    // 定时执行自动采集,定时间隔在 config/timer 控制
    'AutoDown'  => [
        'rows'  => '10'
    ]
];