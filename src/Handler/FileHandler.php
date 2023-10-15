<?php

/**
 * @Author       : <sherri<727145108@qq.com>>
 * @Date         : 2023-10-11 11:07:21
 * @LastEditors  : <sherri<727145108@qq.com>>
 * @LastEditTime : 2023-10-11 11:50:56
 * @package      : Sherri\Statistic\Handler
 * @Class        : FileHandler
 * @Description  :
 *
 * Copyright (c) 2023 by $, All Rights Reserved.
 */

namespace Sherri\Statistic\Handler;


use support\Log;
use Workerman\Timer;

class FileHandler implements HandlerInterface
{

    /**
     * 最大日志buffer，大于这个值就写磁盘
     * @var integer
     */
    const MAX_LOG_BUFFER_SIZE = 1024000;

    /**
     * 多长时间写一次数据到磁盘
     * @var integer
     */
    const LOG_FLUSH_INTERVAL = 60;

    /**
     * 多长时间清理一次老的磁盘数据
     * @var integer
     */
    const LOG_CLEAN_INTERVAL = 86400;

    /**
     * 数据多长时间过期
     * @var integer
     */
    const DATA_EXPIRE_INTERVAL = 1296000;

    /**
     * 统计数据
     * @var array
     */
    protected $statisticData = array();

    /**
     * 日志的buffer
     * @var string
     */
    protected $logBuffer = '';

    /**
     * 放统计数据的目录
     * @var string
     */
    protected static $statisticDir = null;

    /**
     * 存放统计日志的目录
     * @var string
     */
    protected static $logDir = null;

    public function __construct(array $config = array())
    {
        static::$logDir = $config['logDir'] ?? null;
        static::$statisticDir = $config['statisticDir'] ?? null;
        if(!static::$logDir || !static::$statisticDir) {
            throw new \InvalidArgumentException('Invalid Arguments');
        }
        static::initDirectory(static::$logDir);
        static::initDirectory(static::$statisticDir);
    }

    /**
     * 初始化目录
     * @param string $directory
     * @return string
     */
    protected static function initDirectory(string $directory)
    {
        if(!is_dir($directory)) {
            if($directory[strlen($directory)-1] !== DIRECTORY_SEPARATOR) {
                throw new \InvalidArgumentException('The folder must end with /');
            }
            mkdir($directory, 0777, true);
        }
        return $directory;
    }

    /**
     * 读取数据信息
     * @return array
     */
    public function read()
    {
        return static::getProject('test');
    }

    /**
     * 将数据写入磁盘
     * @param array $buffer
     */
    public function write(array $data = array())
    {
        $project = $data['project'];
        $type = $data['type'];
        $action = $data['action'];
        $cost_time = sprintf('%.3f', $data['cost_time']);
        $success = $data['success'];
        $time = $data['time'];
        $code = $data['code'];
        $msg = str_replace("\n", "<br>", $data['msg']);
        $ip = $data['ip'];

        $this->collectStatistics($project, $type, $action, $ip, $cost_time, $success, $code);

        $this->logBuffer .= date('Y-m-d H:i:s', $time)."\t$ip\t$project\t$action\t$success\tcode:$code\t$cost_time\tmsg:$msg\n";
        if(strlen($this->logBuffer) >= self::MAX_LOG_BUFFER_SIZE)
        {
            $this->persist();
        }
    }

    /**
     * 定时写入磁盘
     */
    public function autoPersist() {
        Timer::add(static::LOG_FLUSH_INTERVAL, array($this, 'persistLogger'));
        Timer::add(static::LOG_FLUSH_INTERVAL, array($this, 'persistStatistics'));

        Timer::add(static::LOG_CLEAN_INTERVAL, array($this, 'gc'), array(static::$logDir, static::DATA_EXPIRE_INTERVAL));
        Timer::add(static::LOG_CLEAN_INTERVAL, array($this, 'gc'), array(static::$statisticDir, static::DATA_EXPIRE_INTERVAL));
    }

    /**
     * 日志数据持久化
     */
    public function persistLogger() {
        if(empty($this->logBuffer)) {
            return;
        }
        try {
            file_put_contents(static::$logDir . date('Y-m-d'), $this->logBuffer, FILE_APPEND|LOCK_EX);
        } catch (\Exception $e) {
            Log::error(sprintf('日志数据持久化异常:%s', $e->getMessage()));
        }
        $this->logBuffer = '';
    }

    /**
     * 持久化统计数据
     */
    public function persistStatistics() {
        $time = time();
        foreach ($this->statisticData as $ip => $data) {
            foreach ($data as $project => $items) {
                $file_dir = static::$statisticDir . $project;
                if(!is_dir($file_dir)) {
                    umask(0);
                    mkdir($file_dir, 0777, true);
                }
                foreach($items as $action => $value) {
                    $logger = sprintf("%s\t%s\t%s\t%s\t%s\t%s\t%s\n", $ip, $time, $value['success_count'], $value['success_cost_time'], $value['fail_count'], $value['fail_cost_time'], json_encode($value['code']));
                    try {
                        file_put_contents($file_dir . DIRECTORY_SEPARATOR . $action . date('Y-m-d'), $logger, FILE_APPEND|LOCK_EX);
                    } catch (\Exception $e) {
                        Log::error(sprintf('统计数据持久化异常:%s', $e->getMessage()));
                    }
                }
            }
        }
        //清空统计
        $this->statisticData = array();
    }

    /**
     * 退出前先执行持久化
     * @return mixed|void
     */
    public function close()
    {
        $this->persistLogger();
        $this->persistStatistics();
    }

    /**
     * 清除磁盘数据
     * @param null $file
     * @param int $exp_time
     */
    public function gc($file = null, $exp_time = 86400)
    {
        $time_now = time();
        if(is_file($file)) {
            $mtime = filemtime($file);
            if(!$mtime) {
                Log::warning("Filemtime $file failed");
                return;
            }
            if($time_now - $mtime > $exp_time) {
                unlink($file);
            }
            return;
        }
        foreach(glob($file . '/*') as $filename) {
            $this->gc($filename, $exp_time);
        }
    }

    /**
     * 获取项目信息
     */
    protected static function getProject(string $current_project = '')
    {
        $projects_name_array = array();
        foreach (glob(static::$statisticDir . '/*', GLOB_ONLYDIR) as $filename) {
            $tmp = explode("/", $filename);
            $project = end($tmp);
            $projects_name_array[$project] = array();
            if($current_project == $project) {
                $all_actions = array();
                foreach (glob(static::$statisticDir . $current_project . '/*') as $file){
                    if(is_dir($file)) {
                        continue;
                    }
                    list($action, $date) = explode('_', basename($file));
                    $all_actions[$action] = $action;
                }
                $projects_name_array[$project]  = $all_actions;
            }
        }
        return $projects_name_array;
    }

    /**
     * 统计数据
     * @param string $project
     * @param string $type
     * @param string $action
     * @param string $ip
     * @param string $cost_time
     * @param int $success
     * @param string $code
     */
    protected function collectStatistics(string $project, string $type, string $action, string $ip, string $cost_time, int $success, string $code) {
        if(!isset($this->statisticData[$ip])) {
            $this->statisticData[$ip] = array();
        }
        if(!isset($this->statisticData[$ip][$project])) {
            $this->statisticData[$ip][$project] = array();
        }
        if(!isset($this->statisticData[$ip][$project][$action])) {
            $this->statisticData[$ip][$project][$action] = array('code' => array(), 'success_cost_time' => 0, 'fail_cost_time' => 0, 'success_count' => 0, 'fail_count' => 0);
        }
        if (!isset($this->statisticData[$ip][$project][$action]['code'][$code])) {
            $this->statisticData[$ip][$project][$action]['code'][$code] = 0;
        }
        $this->statisticData[$ip][$project][$action]['code'][$code]++;
        if($success) {
            $this->statisticData[$ip][$project][$action]['success_count']++;
            $this->statisticData[$ip][$project][$action]['success_cost_time'] += $cost_time;
        } else {
            $this->statisticData[$ip][$project][$action]['fail_count']++;
            $this->statisticData[$ip][$project][$action]['fail_cost_time'] += $cost_time;
        }

    }
}
