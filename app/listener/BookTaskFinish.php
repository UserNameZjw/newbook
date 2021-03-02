<?php
declare (strict_types = 1);

namespace app\listener;

class BookTaskFinish
{
    /**
     * 事件监听处理
     *
     * @return mixed
     */
    public function handle($event)
    {
//        var_dump('task finish');
//        var_dump($event[2]);//这里的第三个索引才是onTask传入的data数据

        return ;
    }
}
