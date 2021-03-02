<?php


namespace app\rpc\server;


use app\rpc\interfaces\BookInterfaces;
use Swoole\Server;

class BookServer implements BookInterfaces
{
    public function sendTack($data)
    {
        var_dump($data);
    }
}