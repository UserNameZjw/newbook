<?php
//swoole.task配置文件
return [
    'key' => 'cmd',
    'alias' => [
        'book' => [
            'class' => \app\listener\BookTask::class,
            'methods' => [
                'index',
            ],
            'finish' => false
        ]
    ],
];