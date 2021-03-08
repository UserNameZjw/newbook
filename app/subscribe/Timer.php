<?php


namespace app\subscribe;


use app\v1\controller\BookBase;
use Swoole\Server;

//定时任务事件监听
class Timer
{
    /**
     * 命名规范是on+事件标识,所以该方法的事件名称为event('Timer')
     */
    public function onTimer(Server $server)
    {
        $i = 0 ;
        $bookBase = new BookBase();

        swoole_timer_tick(1000,function ($timer_id) use (&$i,$bookBase){
            go(function() use (&$i,$timer_id,$bookBase){
                
                $redis = $bookBase->getRedisClient();
                $queue = $redis->zrange('queue',0,10);
    
                if(!empty($queue)){
                    $i = 0;
                    foreach ($queue as $key => $value){
                        // 从队列清除 当然一般肯定不会这样做,所以...
                        // 避免重复处理 消耗不必要的资源
                        $redis->zrem('queue',$value);
    
                        go(function () use($value,$bookBase) {
    
                            try{
                                $data  = explode(':',$value);
                                $datas = ['config' => $data[0], 'id' => $data[1],'section' => $data[2]];
        
                                $actionConfig = $bookBase->getActionConfig('article',$datas);
                                $back  = $bookBase->getHtmlList($actionConfig);
        
                                $redis = $bookBase->getRedisClient();
                                // 查询title
                                $title = $redis->hget($datas['config'].':'.$datas['id'],$datas['section']);
                                // 抓取内容存入redis
                                $redis->hsetnx($datas['config'].':'.$datas['id'].':'.$datas['section'],'title',$title);
                                $redis->hmset($datas['config'].':'.$datas['id'].':'.$datas['section'],$back);
        
                                echo $value.'数据处理成功'.PHP_EOL;
                                return ;
                            } catch (\Exception $e){
                                echo $e->getMessage().PHP_EOL;
                            }
    
                        });
                    }
                }
                else {
                    go(function () use (&$i,$timer_id,$bookBase){
                        $redis = $bookBase->getRedisClient();
    
                        if($i >= 10){
                            \Swoole\Timer::clear($timer_id);
                            echo '多次无数据，停止本次定时任务'.PHP_EOL;
                            // 直接删除 定时任务id 避免定时二次启动的时候 出现间隔
                            $redis->del('timer');
                            return ;
                        }
    
                        echo '暂无数据，休眠3秒'.PHP_EOL;
                        sleep(3);
                        $i++;
                    });
                }
            });
        });
    }

}