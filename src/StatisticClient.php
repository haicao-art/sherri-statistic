<?php

/**
 * @Author       : <sherri<727145108@qq.com>>
 * @Date         : 2023-10-11 11:07:21
 * @LastEditors  : <sherri<727145108@qq.com>>
 * @LastEditTime : 2023-10-11 11:50:56
 * @package      : Sherri\Statistic
 * @Class        : Statistic
 * @Description  :
 *
 * Copyright (c) 2023 by $, All Rights Reserved.
 */

namespace Sherri\Statistic;


use Sherri\Statistic\Protocols\Statistic;
use support\Log;

class StatisticClient
{
    /**
     * @var array
     */
    protected static $transferMap = array();

    /**
     * 项目接口上报消耗时间
     * @param string $project
     * @param string $type
     * @param string $action
     * @return float|string
     */
    public static function tick(string $project, string $type, string $action)
    {
        return self::$transferMap[$project][$type][$action] = microtime(true);
    }

    //上报统计数据
    public static function report(string $project, string $type, string $action, int $success, int $code, string $msg)
    {
        if(!config('plugin.sherri.statistic.app.enable', 'false')) {
            self::$transferMap = array();
            return;
        }
        $report_address = config('plugin.sherri.statistic.app.address','udp://127.0.0.1:55656');
        if(isset(self::$transferMap[$project][$type][$action]) && self::$transferMap[$project][$type][$action] > 0) {
            $start_time = self::$transferMap[$project][$type][$action];
            self::$transferMap[$project][$type][$action] = 0;
        } else {
            $start_time = microtime(true);
        }
        $cost_time = microtime(true) - $start_time;
        $buffer = Statistic::encode(['project' => $project, 'type' => $type, 'action' => $action, 'cost_time' => $cost_time, 'success' => $success, 'code' => $code, 'msg' => $msg]);
        return self::sendReport($report_address,$buffer);
    }

    /**
     * 发送数据给统计系统
     * @param string $address
     * @param string $buffer
     * @return bool
     */
    public static function sendReport(string $address, string $buffer): bool
    {
        $socket = stream_socket_client($address);
        if(!$socket) {
            return false;
        }
        return stream_socket_sendto($socket, $buffer) == strlen($buffer);
    }
}
