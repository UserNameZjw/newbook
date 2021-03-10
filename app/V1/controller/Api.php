<?php
namespace app\V1\controller;

use app\proxy\controller\Proxy;
use think\facade\Request;


class Api extends BookBase
{

    protected $version = 'v1';

    public function __call($method, $args)
    {
        return json(['code' => false, 'msg' => '异常访问']);
    }


    /**
     * 搜索接口
     * @return \think\response\Json
     */
    public function search()
    {
        $data = Request::param();
        $back = ['code' => false,'msg' => '异常访问'];

        if(!empty($data['searchtype'] ) && !empty($data['searchkey'])){
            $back = [
                'code'  => true,
                'msg'   => '操作成功'
            ];

            // 把接受到数据 返回
            foreach ($data as $key => $value){
                $back[$key] = $value;
            }

            $data['searchkey'] = urlencode($data['searchkey']);
            $data['config']    = !empty($data['config']) ? $data['config'] : config('book')['default'];

            try {

                $redis      = $this->getRedisClient();
                // 查询伪有效期
                $searchEx   = $redis->get($data['config'].':searchEx:'.md5($data['searchkey']));
                if($searchEx){
                    $search = $redis->smembers($data['config'].':search:'.md5($data['searchkey']));
                }

                if(empty($search)){
                    // 获取相关配置
                    $urlBack      = $this->getActionConfig(Request::action(),$data);
                    $back['list'] = $this->getHtmlList($urlBack);
                    $redis = $this->getRedisClient();
                    foreach ($back['list'] as $key => $value){

                        // 查询定时器是否启动，如果没有 则立即设置为启动状态
                        $timer = $redis->setnx('timer',true);

                        $arr = [
                            'config' => $urlBack['book'],
                            'data'   => [$value],
                            'timer'  => !empty($timer) ? $timer : false
                        ];

                        $back['msg'] = $timer;
                         // 投递到 task
                         try {
                             $this->bookTask($arr);
                         } catch (\Exception $e){
                             $back['msg'] = 'task 异常'.$e->getMessage();
                         }

                        // 设置一次3个月有效期的 key 用于更新本地redis 书籍搜索
                        $redis->setex($data['config'].':searchEx:'.md5($data['searchkey']),7257600,true);
                        $redis->sadd($data['config'].':search:'.md5($data['searchkey']),$value['id']);

                        $redis->lpush('uplist',$data['config'].':config:'.$value['id']);

                    }
                } else {
                    $back['list'] = [];
                    foreach ($search as $key => $value){
                        // 因为数据库不全 并不包含所有书籍
                        $book = $redis->hgetall($data['config'].':config:'.$value);
                        if ($book){
                            $back['list'][] = $book;
                        }
                    }
                }

            } catch (\Exception $e){
                $back['msg'] = '系统异常';
            }
        }

        return json($back);
    }


    /**
     * 书籍详细信息
     * @return \think\response\Json
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function details()
    {
        $data = Request::param();
        $back = ['code' => false,'msg' => '异常访问'];
        $data['config'] = !empty($data['config']) ? $data['config'] : config('book')['default'];

        if(!empty($data['id'])){
            try {

                $redis = $this->getRedisClient();
                $back  = $redis->hgetall($data['config'].':config:'.$data['id']);
                if($back){
                    $back['showreel'] = [];
                    $back['list']     = [];

                    // 查询相关作品
                    $showreel = $redis->smembers($data['config'].':author:'.md5($back['author']));

                    foreach ($showreel as $key => $value) {
                        $book = $redis->hgetall($data['config'].':config:'.$value);
                        // 可能存在查询不到的情况 因为本地库不全
                        if($book){
                            $back['showreel'][$key]['id']    = $book['id'];
                            $back['showreel'][$key]['title'] = $book['title'];
                        }
                    }

                    // 查询章节
                    $list = $redis->hgetall($data['config'].':'.$data['id']);

                    foreach ($list as $key => $value) {
                        $back['list'][$key]['id']    = $key;
                        $back['list'][$key]['title'] = $value;
                    }
                } else {
                    // 获取相关配置
                    $urlBack = $this->getActionConfig(Request::action(),$data);
                    $back    = $this->getHtmlList($urlBack);

                    // 如果是 数据库没有的数据 模拟搜索
                    $proxy = new Proxy();
                    $data = [
                        'searchtype' => 'novelname',
                        'searchkey'  => $back['title']
                    ];

                    $host= $this->getHost('80');

                    $proxy->proxyMian($host['host'],$host['port'],'search',$data,'/api/'.$this->version.'/');
                }

                $back['code'] = true ;
                $back['msg']  = '操作成功';

                // 把接受到数据 返回
                foreach ($data as $key => $value){
                    $back[$key] = $value;
                }

            } catch (\Exception $e){
                $back['msg'] = '系统异常';
            }
        }

        return json($back);
    }


    /**
     * 书籍阅读
     * @return \think\response\Json
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function article()
    {
        $data = Request::param();
        $back = ['code' => false, 'msg' => '异常访问'];

        if(!empty($data['id']) && !empty($data['section'])){
            try {

                $urlBack    = $this->getActionConfig(Request::action(),$data);
                $redis      = $this->getRedisClient();

                // 查询 是否存在redis 数据，字段数 >= 2 title content
                $article    = $redis->hlen($urlBack['book'].':'.$data['id'].':'.$data['section']);
                if($article >= 2){
                    $back   = $redis->hgetall($urlBack['book'].':'.$data['id'].':'.$data['section']);
                } else {
                    // 如果不存在 就抓取
                    $back           = $this->getHtmlList($urlBack);
                    // 查询title
                    $back['title']  = $redis->hget($urlBack['book'].':'.$data['id'],$data['section']);

                    // 抓取内容存入redis
                    $redis->hsetnx($urlBack['book'].':'.$data['id'].':'.$data['section'],'title',$back['title']);
                    $redis->hsetnx($urlBack['book'].':'.$data['id'].':'.$data['section'],'content',$back['content']);
                }

                // 返回所有参数
                foreach ($data as $key => $value){
                    $back[$key] = $value;
                }

                $back['code']   = true;
                $back['msg']    = '查询成功';

            } catch ( \Exception $e) {
                $back = ['code' => false, 'msg' => '系统错误'];
            }
        }
        return json($back);
    }


    /**
     * 在被搜索时 就投递任务到 task 后台处理所有采集 避免耗时
     * @return bool
     */
    public function bookTask($data = [])
    {
        // 设置反代
        $proxy = new Proxy();
        $host= $this->getHost();
        $back = $proxy->proxyMian($host['host'],$host['port'],'bookTask',$data);

        return $back;
    }

    /**
     * 设置 port
     * @param string $port
     * @return array
     */
    public function getHost($port = '')
    {
        $port = !empty($port) ? $port : config('swoole')['server']['port'];

        $host = [
            'host' => explode(':',Request::host())[0],
            'port' => $port
        ];

        return $host;
    }
}
