<?php
return [
    [
        //执行周期(毫秒)
       'tally' => 28800000,
        // 'tally' => 5000,
        //事件名称-注意大小写
        'event' => 'AutoDown',
        //是否等待事件-事件业务完成后开始周期计算
        //注意:在init监听事件中启动队列只能使用等待模式,若要使用非等待模式请改为managerStart事件中启动
        'wait' => false
    ]
];