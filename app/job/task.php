<?php


namespace app\job;


class Task
{
    public function func1($data)
    {
        dump('func1');
        dump($data);
    }

    public function func2($data)
    {
        dump('func2');
        dump($data);
    }
}