<?php


namespace app\listener;


use Swoole\Exception;
use Swoole\Server;

class SwooleBoot
{
    public function handle(Server $server)
    {
        $config = config('private');
//        if ($config['queue_work'])
//            $this->initQueue($server);
        if ($config['timer_work'])
            $this->initTimer($server);
        //对配置文件内的所有class与methods检查
        foreach (config('task.alias') as $alias => $conf) {
            if (!class_exists($conf['class'])) {
                throw new Exception('not found class:' . $conf['class'] . ' ,请检查配置文件!');
            }
            $reflect = new \ReflectionClass($conf['class']);
            foreach ($conf['methods'] as $method) {
                if ($reflect->hasMethod($method) && $reflect->getMethod($method)->isPublic()) {
                    continue;
                } else {
                    throw new Exception('class ' . $conf['class'] . ' method:' . $method . ' attribute err,请检查配置文件/逻辑方法!');
                }
            }
        }
        return;
    }

    private function initTimer(Server $server): void
    {
        $config = config('timer');
        foreach ($config as $conf) {
            if (!$conf['wait']) {
                swoole_timer_tick($conf['tally'], function () use ($conf, $server) {
                    event($conf['event']);
                });
            } else {
                $process = new \Swoole\Process(function ($worker) use ($conf, $server) {
                    swoole_set_process_name('timer ' . $conf['event']);
                    \Co\run(
                        function () use ($conf) {
                            while (true) {
                                event($conf['event']);
                                \Co::sleep(bcdiv($conf['tally'], 1000));
                            }
                        }
                    );
                });
                //managerStart事件直接进行start方法启动线程服务
                //$process->start();
                //init事件监听采用addProcess启动线程服务
                $server->addProcess($process);
            }
        }
    }

    private function initQueue(Server $server): void
    {
        $config = config('boot-queue');
        foreach ($config as $name => $conf) {
            if (!class_exists($conf['conn']['class'])) {
                throw new Exception('not found class:' . $conf['conn']['class'] . ' ,请检查配置文件!');
            }
            $class = new $conf['conn']['class'];
            $reflect = new \ReflectionClass($class);
            if (false === $reflect->hasMethod($conf['conn']['method'])) {
                throw new Exception('not found method:' . $conf['conn']['method'] . ' ,请检查配置文件!');
            }
            $method = $reflect->getMethod($conf['conn']['method']);
            $conn = $method->invoke($class);
            if (!is_object($conn) || $conn instanceof \Redis === false) {
                throw new Exception('class name:' . $conf['conn']['class'] . 'method:' . $conf['conn']['method'] . ' 未获取到连接,请检查配置文件!');
            }
            for ($i = 0; $i < $conf['pop_num']; $i++) {
                $process = new \Swoole\Process(function ($worker) use ($name, $conf, $conn, $i) {
                    swoole_set_process_name($name . '=>' . $conf['process_name'] . ' : ' . $i);
                    $exec = $conf['exec'];
                    if (!class_exists($conf['logic']['class'])) {
                        throw new Exception('not found class:' . $conf['logic']['class'] . ' ,请检查配置文件!');
                    }
                    $logicClass = new $conf['logic']['class'];
                    $reflectLogic = new \ReflectionClass($logicClass);
                    do {
                        try {
                            $pop = $conn->$exec($conf['topics'], $conf['timeout']);
                            if (count($pop) > 0) {
                                //{"method":"test","t":"test"}
                                $data = json_decode($pop[1], true);
                                $logicMethodName = $data[$conf['logic']['call_func_key']];
                                if (false === $reflectLogic->hasMethod($logicMethodName)) {
                                    //未查询到执行方法
                                    throw new Exception('class ' . $conf['logic']['class'] . ' method:' . $logicMethodName . ' not found,请检查配置文件!');
                                }
                                $logicMethod = $reflectLogic->getMethod($logicMethodName);
                                if (!$logicMethod->isPublic() || $logicMethod->isAbstract()) {
                                    throw new Exception('class ' . $conf['logic']['class'] . ' method:' . $logicMethodName . ' attribute err,请检查逻辑方法!');
                                }
                                $logicMethod->invoke($logicClass, $data);
                            } else {
                                sleep($conf['sleep']);
                            }
                        } catch (\Exception $exception) {
                            var_dump($exception->getMessage());
                            if ($conf['err_break']) {
                                var_dump($conf['process_name'] . ':' . $i . " exit");
                                break;
                            }
                        }
                    } while (true);
                });
                //managerStart事件直接进行start方法启动线程服务
                //$process->start();
                //init事件监听采用addProcess启动线程服务
                $server->addProcess($process);
            }
        }
    }
}