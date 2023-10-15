<?php

/**
 * @Author       : <sherri<727145108@qq.com>>
 * @Date         : 2023-10-11 11:07:21
 * @LastEditors  : <sherri<727145108@qq.com>>
 * @LastEditTime : 2023-10-11 11:50:56
 * @package      : Sherri\Statistic\Handler
 * @Interface    : HandlerInterface
 * @Description  :
 *
 * Copyright (c) 2023 by $, All Rights Reserved.
 */


namespace Sherri\Statistic\Handler;


interface HandlerInterface
{
    //gc
    public function gc($file = null, $exp_time = 86400);

    //read
    public function read();

    //write
    public function write(array $data);

    /**
     * 数据持久化
     * @return mixed
     */
    public function persistLogger();

    /**
     * 数据统计持久化
     * @return mixed
     */
    public function persistStatistics();

    /**
     * 数据自动持久化
     * @return mixed
     */
    public function autoPersist();

    /**
     * 关闭
     * @return mixed
     */
    public function close();
}
