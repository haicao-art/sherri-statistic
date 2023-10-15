<?php

/**
 * @Author       : <sherri<727145108@qq.com>>
 * @Date         : 2023-10-11 11:07:21
 * @LastEditors  : <sherri<727145108@qq.com>>
 * @LastEditTime : 2023-10-11 11:50:56
 * @package      : Sherri\Statistic
 * @Class        : StatisticWorker
 * @Description  :
 *
 * Copyright (c) 2023 by $, All Rights Reserved.
 */

namespace Sherri\Statistic;


use support\Log;
use Workerman\Timer;
use Workerman\Worker;

class StatisticWorker extends Worker
{

    /**
     * 当 worker 启动时
     *
     * @var callable|null
     */
    protected $_onWorkerStart = null;

    /**
     * 当有客户端连接时
     *
     * @var callable|null
     */
    protected $_onConnect = null;

    /**
     * 当客户端发来消息时
     *
     * @var callable|null
     */
    protected $_onMessage = null;

    /**
     * 当客户端连接关闭时
     *
     * @var callable|null
     */
    protected $_onClose = null;

    /**
     * 当 worker 停止时
     *
     * @var callable|null
     */
    protected $_onWorkerStop = null;

    /**
     * 保存用户设置的 workerReload 回调
     *
     * @var callable|null
     */
    protected $_onWorkerReload = null;

    /**
     * 进程启动时间
     *
     * @var int
     */
    protected $_startTime = 0;

    /**
     * 提供统计查询的socket
     * @var resource
     */
    protected $providerSocket = null;

    /**
     * handler instance.
     */
    protected static $_handler = null;

    /**
     * Handler class which implements HandlerInterface.
     *
     * @var string
     */
    protected static $_handlerClass = null;

    /**
     * Parameters of __constructor for handler class.
     *
     * @var null
     */
    protected static $_handlerConfig = null;

    /**
     * 构造函数
     */
    public function __construct($config = array())
    {
        foreach ($config as $key => $value)
        {
            $this->$key = $value;
        }
        parent::__construct();
        // 保存用户的回调，当对应的事件发生时触发
        $this->_onWorkerStart = $this->onWorkerStart;
        // 保存用户的回调，当对应的事件发生时触发
        $this->_onWorkerReload = $this->onWorkerReload;
        // 保存用户的回调，当对应的事件发生时触发
        $this->_onWorkerStop = $this->onWorkerStop;
        $this->onWorkerStart   = array($this, 'onWorkerStart');
        $this->onWorkerStop   = array($this, 'onWorkerStop');
        $this->onWorkerReload  = array($this, 'onWorkerReload');

        // 记录进程启动的时间
        $this->_startTime = time();
    }

    /**
     * 业务启动
     */
    public function onWorkerStart(Worker $worker)
    {
        umask(0);
        if ($this->_onWorkerStart) {
            call_user_func($this->_onWorkerStart, $this);
        }
        $type = config('plugin.sherri.statistic.app.type', 'file');
        $defaultConfig = config('plugin.sherri.statistic.app.default')[$type] ?? [];
        if(!isset($defaultConfig['class']) || !isset($defaultConfig['config'])) {
            throw new \InvalidArgumentException('Invalid config Error');
        }
        static::$_handlerClass = $defaultConfig['class'];
        static::$_handlerConfig = $defaultConfig['config'];
        if (static::$_handler === null) {
            if (static::$_handlerConfig === null) {
                static::$_handler = new static::$_handlerClass();
            } else {
                static::$_handler = new static::$_handlerClass(static::$_handlerConfig);
            }
        }
        if ($worker->id === 0) {
            //定时数据持久化
            static::$_handler->autoPersist();
        }

        $result = static::$_handler->read();
        //var_dump($result);
        //初始化目录 清理不用的数据
    }

    /**
     * 业务处理
     * @param $connection
     * @param $data
     */
    public function onMessage($connection, $data)
    {
        static::$_handler->write(array_merge($data, ['ip' => $connection->getRemoteIp()]));
    }

    /**
     * onWorkerReload 回调
     *
     * @param Worker $worker
     */
    protected function onWorkerReload($worker)
    {
        static::$_handler->close();
        // 防止进程立刻退出
        $worker->reloadable = false;
        // 延迟 0.05 秒退出，避免 Worker 瞬间全部退出导致没有可用的 BusinessWorker 进程
        Timer::add(0.05, array('Workerman\Worker', 'stopAll'));
        // 执行用户定义的 onWorkerReload 回调
        if ($this->_onWorkerReload) {
            call_user_func($this->_onWorkerReload, $this);
        }
    }

    /**
     * 当进程关闭时一些清理工作
     *
     * @return void
     */
    protected function onWorkerStop()
    {
        static::$_handler->close();

        if ($this->_onWorkerStop) {
            call_user_func($this->_onWorkerStop, $this);
        }
    }
}
