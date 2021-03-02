<?php


namespace app\job;


use think\facade\Log;
use think\queue\Job;

class Job1
{
    public function fire(Job $job, $data)
    {
        try {
            $data['now']=time();
            // 日志
            Log::info(json_encode($data, 320));
            // 如果任务执行成功后 记得删除任务，不然这个任务会重复执行，直到达到最大重试次数后失败后，执行failed方法
            $job->delete();
            // 也可以重新发布这个任务
            // $job->release($delay = 0); // $delay 为延迟时间，单位为秒
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function failed($data)
    {
        // ...任务达到最大重试次数后，失败了
    }
}