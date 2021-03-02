<?php
declare (strict_types=1);

namespace app\listener;


use Swoole\Server;
use Swoole\Server\Task;
use think\Container;
use think\swoole\Table;

class SwooleTask
{

    protected $server = null;
    protected $table = null;
    protected $key = '';
    protected $alias = [];

    public function __construct(Server $server, Container $container)
    {
        $this->server = $server;
        $this->table = $container->get(Table::class);
        $this->key = config('task.key');
        $this->alias = config('task.alias');
    }

    /**
     * 事件监听处理
     *
     * @return mixed
     */
    public function handle(Task $task)
    {
        if (isset($task->data[$this->key]) && isset($this->alias[$task->data[$this->key]])) {
            $class = new $this->alias[$task->data[$this->key]]['class'];
            $methods = $this->alias[$task->data[$this->key]]['methods'];
            foreach ($methods as $method) {
                $class->$method($task->data);
            }
            if ($this->alias[$task->data[$this->key]]['finish']) {
                $task->finish($task->data);
            }
        } else {
//            dump($task->data);
            dump('未定义的task任务:' . json_encode($task->data, 320));
        }
        return;
    }

    protected function ping()
    {
        foreach ($this->table->u2fd as $row) {
            $this->server->push($row['fd'], 'ping');
        }
    }
}
