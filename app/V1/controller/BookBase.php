<?php


namespace app\V1\controller;

use Predis\Client as RedisClient;
use think\facade\Config;
use think\facade\Request as TpRequest;
use think\facade\Cache;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Symfony\Component\DomCrawler\Crawler;
use Yurun\Util\Swoole\Guzzle\SwooleHandler;

use Swoole\Coroutine\Barrier;

class BookBase
{

    protected $url      = '';
    protected $response = '';

    /**
     * 统一配置
     * @param mixed $config  额外配置
     * @return Client
     */
    protected function client($config = []){

        $conf = [
            'timeout' => 10,
            'headers' => [
                'User-Agent'        => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.88 Safari/537.36 Edg/87.0.664.57',
                'Content-Type'      => 'text/plain; charset=utf-8',
                'Accept-Language'   => 'zh-CN,zh-TW;q=0.9,zh;q=0.8,en;q=0.7,en-GB;q=0.6,en-US;q=0.5',
            ]
        ];

        if(!empty($config)){
            foreach ($config as $key => $value){
                $conf = array_merge($conf,$value);
            }
        }

        $client  = new Client($conf);
        return $client;
    }

    /**
     * 获取搜索列表
     * @param string $urlBack 配置规则
     * @param array $arr 页面数据，主要是task ，其他不需要
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getHtmlList($urlBack,$arr = [])
    {
        $this->setUrl($urlBack['url']);

        if($urlBack['flow']){
            $this->getHtmlFlow();
        } else {
            $this->getHtmlClient($urlBack['url']);
        }

        $list = $this->getAll($urlBack['xpath']['xpath'],$urlBack,$arr);
        return $list;
    }

    /**
     * 根据规则返回对应键名的结果
     * @param object $xpath 最外围 xpath
     * @param array  $arr   规则配置
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */

    public function getAll($xpath,$config,$data = [])
    {
        $list = [];
        // 如果是所有的获取元素 都在同一个 可被循环的 节点中 ，则 直接循环
        if($config['xpath']['each']){
            $list = $this->getXpathEach($xpath,$config['arr']);
        } else {
            // 循环数组
            foreach ($config['arr'] as $key => $value){
                // uni数据编码 接口
                if(!empty($config['xpath']['uni'])){
                    if(!empty($this->response)){
                        $uniCode    = $this->unicodeDecode($this->response);
                        $list[$key] = $uniCode[$value];
                    }
                } else {
                    //结构化数据存本数组
                    $crawler    = new Crawler();
                    $crawler->addHtmlContent($this->response);
                    // 如果是循环。
                    if($value['each']) {
                        $list[$key] = $this->getXpathEach($value['xpath'], $value['arr']);
                    } else {
                        $list[$key] = $this->getXpath($crawler,$value['xpath'],$moth = 'text',$str ='');
                    }
                }
            }
        }

        return $list;
    }


    /**
     * 循环某个节点 抓取对应内容
     * @param string $xpath    循环节点
     * @param array $arr      数据模型字段抓取规则
     * @return array
     */
    public function getXpathEach($xpath,$arr)
    {
        $list = [];
        //结构化数据存本数组
        $crawler  = new Crawler();
        $crawler->addHtmlContent($this->response);
        $crawler->filterXPath($xpath)
            ->each(function (Crawler $node, $i) use (&$list,$arr) {
                // 循环模型 按照规则抓取
                foreach ($arr as $key => $value){

                    $moth  = !empty($value['moth']) ? $value['moth'] :'text';
                    $str   = !empty($value['str']) ? $value['str'] :'';
                    // 如果是函数
                    if(!empty($value['fun'])){
                        // 如果函数传递的是 数组 则认定 name 和 param
                        if(is_array($value['fun'])){
                            $list_s[$key] = $this->{ $value['fun']['name'] }(
                                $this->getXpath($node,$value['xpath']['xpath'],$moth,$str),$value['fun']['param']
                            );
                        }else{
                            // 正常字符串
                            $list_s[$key] = $this->{ $value['fun'] }(
                                $this->getXpath($node,$value['xpath']['xpath'],$moth,$str)
                            );
                        }

                    } else {
                        $list_s[$key] = $this->getXpath($node,$value['xpath'],$moth,$str);
                    }

                    $list[$i] = $list_s;
                }
            });

        return $list;
    }


    /**
     * 获取节点信息
     * @param Object $node      html 原型对象
     * @param string $xpath     抓取节点
     * @param string $moth      调用的方法
     * @param string $str       属性值
     * @return mixed
     */
    public function getXpath($node,$xpath,$moth = 'text',$str ='')
    {
        return $node->filterXPath($xpath)->{$moth}($str);
    }


    /**
     * @param string $url  需要抓取页面的url
     * @param bool   $code 是否全局变量
     * @param string $meth 请求方式
     * @param array  $data 需要发送的数据
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getHtmlClient($url,$meth = 'GET',$data = [])
    {
        $this->setUrl($url);
        $response = $this->client()->request($meth, $this->url,$data)->getBody()->getContents();
        $this->setResponse($response);
    }


    /**
     * task 专属调用
     * @param array $config  配置规则
     * @param mixed $arr
     */
    public function getHtmlFlow()
    {
        // 本模式仅适用于 swoole task 自动任务采集所有文章内容时使用。
        // 用定时器实现队列功能
        $barrier = Barrier::make();
        $response = [];

        go(function () use ($barrier,&$response) {

            $handler = new SwooleHandler();
            $stack   = HandlerStack::create($handler);

            $client  = new Client([
                'handler' => $stack,
                'verify'  => false
            ]);

            $response = $client->request('GET',$this->url)->getBody()->getContents();
        });

        Barrier::wait($barrier);

        $this->setResponse($response);

    }


    /**
     * 转码
     * @param $str
     * @return false|string|string[]|void|null
     */
    public function mbToUtf8($str)
    {
        return mb_convert_encoding($str, 'UTF-8', 'UTF-8');
    }

    /**
     * 获取当前方法名
     * @param string $action   当前方法
     * @param array  $data     接收到 GET 请求的数据
     * @return array    ['book' => 站点标识 ，'url' => 功能的url , 'xpath' => 主节点 , 'arr' => 功能抓取对应规则 , 'flow' => 是否流]
     */
    public function getActionConfig($action,$data)
    {
        // 如果没有传入 config 默认为 配置
        $data['config'] = !empty($data['config']) ? $data['config'] : Config::get('book')['default'];

        // 获取 标识 配置
        $config = Config::get('book')[$data['config']];
        // 获取当前 方法 配置
        $actionConfig = $config[$action];
        $urlParam     = '';
        // 组合url 如果是数组 则进行处理 主要是因为 数据 需要组合在 url 里 而不是在后拼接
        if(is_array($actionConfig['url'])){
            foreach ($actionConfig['url'] as $key => $value){
                // 如果 键名 是 data 则 说明调用 接收到数据进行组合  把data 对应的 键值 作为组合
                if(strpos($key,'data') !== false){
                    $urlParam = $urlParam.$data[$value];
                } else {
                    $urlParam = $urlParam.$value;
                }
            }
        } else {
            $urlParam = $actionConfig['url']."?";
        }

        // 拼接参数
        if(!empty($actionConfig['param'])){
            foreach ($actionConfig['param'] as $key => $value){
                $urlParam = $urlParam.'&'.$key.'='.$data[$key];
            }
        }

        // 返回抓取 url 以及 方法对应的抓取规则
        $back = [
            'book'   => $data['config'],
            'action' => $action,
            'url'    => $config['url'].$urlParam,
            'xpath'  => $actionConfig['xpath'],
            'arr'    => $actionConfig['arr'],
            'flow'   => TpRequest::port() != 80 ? true : false
        ];

        return $back;
    }

    /**
     * 获取 redis 类 方便配置
     * @return RedisClient
     */
    public function getRedisClient()
    {
        $redis = Cache::store();

        return $redis;
    }


    /**
     * 获取数字
     * @param  string $str  替换的字符串
     * @return string|string[]|null
     */
    public function getNumber($str)
    {
        return preg_replace('/[^0-9]/', '',$str);
    }


    /**
     * 获取url 的截断
     * @param string $url   需要截取的url
     * @param mixed ...$arr 不定参，如果传递必须按照  ['index' => 2,'index_tow' => 0]
     * @return mixed
     */
    public function getLinkUrl($url, ...$arr){
        $arr = !empty($arr) ? $arr[0] : ['index' => 2,'indexTow' => 0];
        return explode('.',explode('/',$url)[$arr['index']])[$arr['indexTow']];
    }


    /**
     * 设置 $url
     * @param string $url 需要设置的url
     */
    public function setUrl ($url){
        $this->url = $url;
    }


    /**
     * 设置 $response
     * @param string $response
     */
    
    public function setResponse ($response){
        $this->response = $response;
    }


    /**
     * unicode 解码
     * @param $data
     * @return string|string[]|null
     */
    public function unicodeDecode($data)
    {
        $arr = json_decode($data, true);
        return $arr;
    }
}