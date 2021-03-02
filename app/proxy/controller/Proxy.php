<?php


namespace app\proxy\controller;

use app\v1\controller\BookBase;
use Swoole\Server;
use think\facade\Event;
use think\facade\Request;
use think\swoole\Manager;

class Proxy
{
    protected $host = '';
    protected $port = '';
    protected $path = '';
    protected $meth = '';
    protected $data = '';

    public function __call($method,$args)
    {
        return json(['code' => false, 'msg' => '异常访问']);
    }

    /**
     * 规定对外统一调用端口
     * @param $host
     * @param $port
     * @param $meth
     * @param array $data
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function proxyMian($host,$port,$meth,$data =[],$path = '/proxy/proxy/')
    {
        $this->setHost($host);
        $this->setPort($port);
        $this->setPath($path);
        $this->setMeth($meth);
        $this->setData($data);

        return $this->run();
    }


    /**
     * 统一 运行端口
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function run(){

        $bookBase = new BookBase();
        $url = $this->host.':'.$this->port.$this->path.$this->meth;
        $data = [
            'form_params' => $this->data
        ];

        $bookBase->getHtmlClient($url,'POST',$data);

        return true;
    }

    /**
     * 投递 bookTask
     * @param Server $server
     */
    public function bookTask(Server $server)
    {
        $post = Request::post();

        $server->task(['cmd' => 'book', 'data' => $post]);
    }



    /**
     * 设置 访问域名
     * @param string $host
     */
    public function setHost($host){
        $this->host = $host;
    }

    /**
     * 设置 端口号
     * @param string $port
     */
    public function setPort($port){
        $this->port = $port;
    }

    /**
     * 设置 路径
     * @param string $path
     */
    public function setPath($path){
        $this->path = $path;
    }

    /**
     * 设置 $meth 方法
     * @param string $meth
     */
    public function setMeth($meth){
        $this->meth = $meth;
    }

    /**
     * 设置 $data 数据
     * @param array $data
     */
    public function setData($data){
        $this->data = $data;
    }
}