<?php
namespace library\service;

use Pheanstalk\Exception\ServerException;
use Pheanstalk\Job;
use Pheanstalk\Pheanstalk;
use think\Exception;
use think\facade\Env;
use think\facade\Log;
use think\facade\Config;

/**
 * Desc: 延时消息队列服务类
 */

class QueueService
{
    /**
     * @var Pheanstalk
     */
    private $pheanstalk;

    /**
     * key 消息队列名称
     * method app\common\service\Consumer 对应消费方法名称
     * desc 队列描述信息
     * @var array
     */
    private $tubeArr = [];

    /**
     *
     */
    private $config = [
        // 队列服务地址
        'host' => '',
        // 队列服务端口
        'port' => 11300,
        // 管道列表，Key是管道名称，Value是消费类配置 eg:'TEST' => array('consumer'=>Test::class, 'desc'=>'测试消息队列')
        'tubes' => [],
        // 异常消息重发策略
        'plot' => [10, 30, 60, 120],
        // 开启debug模式会记录日志信息
        'debug' => true,
        // 日志级别，设置后需要在config/log.php添加设置记录目录的类型和日志类型分别写入文件
        'loglevel' => 'queue'
    ];

    /**
     * Queue constructor.
     */
    public function __construct()
    {
        Config::load(Env::get('CONFIG_PATH'). 'queue.php', 'queue');
        $this->config = array_merge($this->config, Config::get('queue.'));
        $this->pheanstalk = new Pheanstalk($this->config['host'],$this->config['port']);
        $this->tubeArr = array_change_key_case($this->config['tubes'], CASE_UPPER);
        $this->setWatchTube();
    }

    /**
     * 设置关注的管道列表
     * @param string $ignore
     */
    public function setWatchTube ($ignore = 'default')
    {
        foreach ($this->tubeArr as $k=>$tube) $this->pheanstalk->watch($k);
        $this->pheanstalk->ignore($ignore);
    }

    /**
     * 是否是活跃可用
     * @return false|true
     */
    public function isAlive(){
        return $this->pheanstalk->getConnection()->isServiceListening();
    }

    /**
     * 查看beanstalkd状态情况
     * @return object|\Pheanstalk\Response
     */
    public function status(){
        return $this->pheanstalk->stats() ;
    }

    /**
     * 获取服务信息
     * @return array
     */
    public function getServiceInfo()
    {
        return array_merge(['host'=>$this->config['host'],'port'=>$this->config['port']],json_decode(json_encode($this->status(), true), true));
    }

    /**
     * 向管道里写入信息[入队]
     * @param $tubeName
     * @param $data
     * @param int $delay
     * @param int $priority
     * @param int $ttr
     * @return array
     * @throws Exception
     */
    public function pushMsg($tubeName, $data, $delay=0, $priority=1024, $ttr=60)
    {
        // 检查管道是否合法
        if(!array_key_exists($tubeName, $this->tubeArr)) throw new Exception("非法队列名称");
        // 检查消息内容
        if (!is_array($data) || empty($data)) throw new Exception("消息内容不能为空");
        // 创建消息内容
        $msgBody = ['tag' => $tubeName, 'data'=>$data, 'create_at' => date('Y-m-d H:i:s',time())];
        // 入队
        $jobId = $this->pheanstalk->useTube($tubeName)->put(json_encode($msgBody), $priority, $delay, $ttr);
        $this->saveLog($jobId, '消息入队成功', $msgBody);
        return $jobId;
    }

    /**
     * 从管道中获取消息[出队]
     */
    public function getMsg()
    {
        $job = $this->pheanstalk->reserve();
        if($job) $this->dealWithMsg($job);
    }

    /**
     * @param Job $job
     * @return bool
     */
    private function dealWithMsg (Job $job)
    {
        $jobId = $job->getId(); $jobData = $job->getData();
        $jobDataArr = json_decode($jobData,true);
        // 检查消息数据
        if (!isset($jobDataArr['tag']) || !array_key_exists($jobDataArr['tag'], $this->tubeArr)) {
            $this->pheanstalk->delete($job);
            $this->saveLog($jobId, '消息处理失败，消息管道不存在！', $jobDataArr);
            return false;
        }
        // 检查对应消费方法是否存在
        $consumeClass = $this->tubeArr[$jobDataArr['tag']]['consumer'];
        if (!method_exists($consumeClass, 'run')) {
            $this->pheanstalk->delete($job);
            $this->saveLog($jobId, '消息处理失败，消息消费者不存在！', $jobDataArr);
            return false;
        }
        // 处理数据
        $ret = call_user_func(array(new $consumeClass, 'run'), $jobDataArr);
        // 判断处理结果，消费方法返回recode=1时，表示处理成功，其他为失败
        if (!is_array($ret) || !isset($ret['recode']) || 1 != $ret['recode']) {
            $this->releaseByPlot($job);
            $this->saveLog($jobId, '消息处理失败，' . $ret['recode'], $jobDataArr);
            return false;
        }
        // 处理成功，删除消息
        $this->pheanstalk->delete($job);
        $this->saveLog($jobId, '消息处理成功', $jobDataArr);
    }

    /**
     * 通过配置的策略处理异常信息
     * @param Job $job
     */
    public function releaseByPlot(Job $job)
    {
        // 获取任务信息
        $statsJob = json_decode(json_encode($this->pheanstalk->statsJob($job)), true);
        // 获取策略配置和消息已执行次数
        $plot = $this->config['plot'];$reserves = $statsJob['reserves'];
        // 依据异常策略，重置任务
        if ($reserves >= count($plot)) {
            $this->pheanstalk->bury($job);
        } else {
            $this->pheanstalk->release($job, 1024, $plot[$reserves]);
        }
    }

    /**
     * 获取所有管道列表
     * @return array
     */
    public function listTubes()
    {
        return $this->pheanstalk->listTubes();
    }

    /**
     * 获取关注管道列表
     * @return array
     */
    public function listTubesWatched()
    {
        return $this->pheanstalk->listTubesWatched();
    }

    /**
     * 查看管道状态
     * @return array
     */
    public function statsTubes()
    {
        $tubes = [];
        foreach ($this->listTubesWatched() as $tube) {
            $desc = array_key_exists($tube, $this->tubeArr) ? $this->tubeArr[$tube]['desc'] : '未在当前系统内使用';
            array_push($tubes, array_merge(['desc'=>$desc], $this->statsTubeByName($tube)));
        }
        return $tubes;
    }

    /**
     * 根据名称查看管道状态
     * @param $tube
     * @return array
     */
    public function statsTubeByName($tube)
    {
        return json_decode(json_encode($this->pheanstalk->statsTube($tube), true), true);
    }

    /**
     * 暂停管道
     * @param $tube
     * @param $delay
     */
    public function pauseTube($tube, $delay)
    {
        $this->pheanstalk->pauseTube($tube, $delay);
    }

    /**
     * 恢复管道
     * @param $tube
     */
    public function resumeTube($tube)
    {
        $this->pheanstalk->pauseTube($tube, 0);
    }

    /**
     * 获取消息
     * @param $jobId
     * @return object|Job
     */
    public function peek($jobId)
    {
        return $this->pheanstalk->peek($jobId);
    }

    /**
     * 重置任务
     * @param $jobId
     * @return bool
     */
    public function release($jobId)
    {
        try {
            $this->pheanstalk->kickJob($this->peek($jobId));
            return true;
        } catch (ServerException $serverException) {
            echo $serverException->getMessage();
            return false;
        }
    }

    /**
     * 删除任务
     * @param $jobId
     * @return bool
     */
    public function delete($jobId)
    {
        try {
            $this->pheanstalk->delete($this->peek($jobId));
            return true;
        } catch (ServerException $serverException) {
            echo $serverException->getMessage();
            return false;
        }
    }

    /**
     * 将消息放入Ready
     * @param $jobId
     * @return bool
     */
    public function exec($jobId)
    {
        try {
            $this->pheanstalk->kickJob($this->peek($jobId));
            return true;
        } catch (ServerException $serverException) {
            echo $serverException->getMessage();
            return false;
        }
    }

    /**
     * 获取下一条保留消息
     * @param $tube
     * @return object|Job
     */
    public function nextBuriedJobInfo($tube)
    {
        try {
            $job = $this->pheanstalk->peekBuried($tube);
            $jobInfo = json_decode(json_encode($this->pheanstalk->statsJob($job)), true);
            $jobInfo['data'] = json_decode($job->getData(),true);
            return $jobInfo;
        } catch (ServerException $serverException) {
            return [];
        }
    }

    /**
     * 获取下一条待处理消息
     * @param $tube
     * @return object|Job
     */
    public function nextReadyJobInfo($tube)
    {
        try {
            $job = $this->pheanstalk->peekReady($tube);
            $jobInfo = json_decode(json_encode($this->pheanstalk->statsJob($job)), true);
            $jobInfo['data'] = json_decode($job->getData(),true);
            return $jobInfo;
        } catch (ServerException $serverException) {
            return [];
        }
    }

    /**
     * 获取下一条延时消息
     * @param $tube
     * @return object|Job
     */
    public function nextDelayJobInfo($tube)
    {
        try {
            $job = $this->pheanstalk->peekDelayed($tube);
            $jobInfo = json_decode(json_encode($this->pheanstalk->statsJob($job)), true);
            $jobInfo['data'] = json_decode($job->getData(),true);
            return $jobInfo;
        } catch (ServerException $serverException) {
            return [];
        }
    }

    /**
     * 记录日志
     * @param $jobId
     * @param $message
     * @param array $msgData
     */
    private function saveLog ($jobId, $message, $msgData = array())
    {
        try {
            if ($this->config['debug']) {
                $content = "----------------------------------------------------------------" . PHP_EOL;
                $content .= "消息ID:" . $jobId . PHP_EOL;
                $content .= "描述内容:" . $message . PHP_EOL;
                $content .= "消息内容:" . $msgData?json_encode($msgData):"无" . PHP_EOL;
                Log::record($content, $this->config['loglevel']);
            }
        } catch (Exception $e) {}
    }
}
