<?php
declare (strict_types = 1);

namespace app\listener;


use app\v1\controller\BookBase;


class BookTask extends BookBase
{

    /**
     * 事件监听处理
     *
     * @return mixed
     */

    public function index($data)
    {

        $data   = $data['data']['form_params'];
        $config = $data['config'];
        foreach ($data['data'] as $key => $value){
            // 协程录入所有 查询出来的书籍列表
            go(function () use ($config,$value){
                $this->search($config,$value);
            });

            // 录入书籍详情
            go(function () use ($config,$value){
                // 模拟书籍详情传递的参数
                $data = [
                    'id'     => $value['id'],
                    'config' => $config
                ];

                $this->details($data);
            });

            // 启动定时器
            if($data['timer']){
                $this->startTimer();
            }
        }

        return ;
    }


    /**
     * 录入 书籍信息
     * @param $config
     * @param $value
     */
    public function search($config,$value)
    {
        // 存redis
        go(function () use ($config,$value){
            $redis = $this->getRedisClient();

            // 书籍id 录入分类集合
            $redis->sadd($config.':'.$value['typeUrl'],$value['id']);
            // 存入作者相关作品集
            $redis->sadd($config.':author:'.md5($value['author']),$value['id']);

            // 书籍详情配置
            // 后续详情页 添加 书籍简介 本页面无法采集
            $redis->hmset($config.':config:'.$value['id'],$value);
        });
    }


    /**
     * 处理详情页面所有能抓的数据
     * @param $data
     */

    public function details($data)
    {
        $action  = __FUNCTION__;

        $urlBack = $this->getActionConfig($action,$data);
        $back    = $this->getHtmlList($urlBack);

        // 补全书籍简介
        go(function () use ($urlBack,$back,$data){
            $redis   = $this->getRedisClient();
            // 简介
            $redis->hsetnx($urlBack['book'].':config:'.$data['id'],'intro',$back['intro']);
            // 最后更新时间
            $redis->hset($urlBack['book'].':config:'.$data['id'],'uptime',substr($back['uptime'],9));
        });

        // 作品集储存
        go(function () use ($urlBack,$back,$data){
            $redis   = $this->getRedisClient();

            foreach ($back['showreel'] as $key => $value){
                $redis->sadd($urlBack['book'].':author:'.md5($back['author']),$value['id']);
            }
        });

        // 章节储存
        go(function () use ($urlBack,$back,$data){
            $redis   = $this->getRedisClient();
            foreach ($back['list'] as $key => $value){
                $redis->hsetnx($urlBack['book'].':'.$data['id'],$value['sectionId'],$value['section']);

                // 查看字段长度，如果大于2 则说明存在 否则存入队列
                $hlen = $redis->hlen($urlBack['book'].':'.$data['id'].':'.$value['sectionId']);
                if($hlen < 2){
                    $redis->zadd('queue',microtime(true),$urlBack['book'].':'.$data['id'].':'.$value['sectionId']);
                }
            }
        });
    }


    /**
     * 启动定时器
     */
    public function startTimer()
    {
        event('Timer');
    }

}
