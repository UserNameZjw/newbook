<?php


namespace app\subscribe;

use app\V1\controller\BookBase;
use Swoole\Server;

class AutoDown extends BookBase
{
    /**
     * 自动更新程序
     * 如果没有队列 则不执行
     * @param Server $server
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function onAutoDown(Server $server)
    {
        $redis = $this->getRedisClient();
        // 查询队列长度
        $llen  = $redis->llen('uplist');
        if($llen){
            $rows  = config('bookconfig')['AutoDown']['rows']; // 进率
            $page  = 0;
            $limt  = $rows;
            $i     = ceil($llen/$rows);

            for ($x=0; $x<$i; $x++) {
                if($x > 0){
                    $page = $limt+1;
                    $limt = $page+$rows;
                }

                $list = $redis->lrange('uplist',$page,$limt);

                foreach ($list as $keys => $bookv) {
                    go(function () use ($bookv,$server){
                        $bookConfigKey = explode(':',$bookv);
                        $data          = [
                            'id'       => $bookConfigKey['2'],
                            'config'   => $bookConfigKey['0'],
                        ];

                        $urlBack    = $this->getActionConfig('details',$data);
                        $back       = $this->getHtmlList($urlBack);

                        $redis      = $this->getRedisClient();
                        $bookConfig = $redis->hgetall($bookv);
                        $newTime    = substr($back['uptime'],9,10);

                        // 如果更新时间跟实际库不一致
                        if($bookConfig['uptime'] <> $newTime){

                            // 查询定时器是否启动，如果没有 则立即设置为启动状态
                            $timer = $redis->setnx('timer',true);

                            $arr = [
                                'config' => $urlBack['book'],
                                'data'   => [$bookConfig],
                                'timer'  => $timer
                            ];

                            // 直接投递到task 统一处理入口
                            $server->task(['cmd' => 'book', 'data' => $arr]);

                        }

                        // 如果60天都没有新的更新，则从列表删除
                        $date = date('Y-m-d',strtotime('-60 day'));

                        if($newTime < $date){
                            $redis->lrem('uplist',$bookv,0);
                        }
                    });
                }
            }
        }

    }
}