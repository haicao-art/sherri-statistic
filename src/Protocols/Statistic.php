<?php

/**
 * @Author       : <sherri<727145108@qq.com>>
 * @Date         : 2023-10-11 11:07:21
 * @LastEditors  : <sherri<727145108@qq.com>>
 * @LastEditTime : 2023-10-11 11:50:56
 * @package      : Sherri\Statistic\Protocols
 * @Class        : Statistic
 * @Description  :
 *
 * Copyright (c) 2023 by $, All Rights Reserved.
 */

namespace Sherri\Statistic\Protocols;

/**
 *
 * struct statisticPortocol
 * {
 *     unsigned char project_name_length;
 *     unsigned char type_name_length;
 *     unsigned char action_name_length;
 *     float cost_time;
 *     unsigned char success;
 *     int code;
 *     unsigned short msg_len;
 *     unsigned int time;
 *     char[project_name_length] project_name;
 *     char[type_name_length] type_name;
 *     char[action_name_length] action_name;
 *     char[msg_len] msg;
 * }
 *
 */
class Statistic
{
    /**
     * 包头长度
     * @var integer
     */
    const PACKAGE_HEADER_LENGTH = 18;

    /**
     * udp包最大长度
     * @var integer
     */
    const UDP_PACKAGE_MAX_LENGTH = 65507;

    /**
     * char类型保存最大数值
     * @var integer
     */
    const MAX_CHAR_VALUE = 255;

    /**
     * usigned short 保存最大数值
     * @var integer
     */
    const MAX_UNSIGNED_SHORT_VALUE = 65535;

    /**
     * @func input
     * @param string $recv_buffer
     */
    public static function input(string $recv_buffer)
    {
        if(strlen($recv_buffer) < self::PACKAGE_HEADER_LENGTH) {
            return 0;
        }
        $data = unpack("Cproject_name_len/Ctype_name_len/Caction_name_len/fcost_time/Csuccess/Ncode/nmsg_len/Ntime", $recv_buffer);
        return $data['project_name_len'] + $data['type_name_len'] + $data['action_name_len'] + $data['msg_len'] + self::PACKAGE_HEADER_LENGTH;
    }

    /**
     * 编码
     * @param array $data
     * @return string
     */
    public static function encode(array $data)
    {
        $project = $data['project'];
        $type = $data['type'];
        $action = $data['action'];
        $cost_time = $data['cost_time'];
        $success = $data['success'];
        $code = $data['code'] ?? 0;
        $msg = $data['msg'] ?? '';

        //防止模块名过长
        if(strlen($project) > self::MAX_CHAR_VALUE) {
            $project = substr($project, 0, self::MAX_CHAR_VALUE);
        }
        if(strlen($type) > self::MAX_CHAR_VALUE) {
            $type = substr($type, 0, self::MAX_CHAR_VALUE);
        }
        if(strlen($action) > self::MAX_CHAR_VALUE) {
            $action = substr($action, 0, self::MAX_CHAR_VALUE);
        }
        $project_name_length = strlen($project);
        $type_name_length = strlen($type);
        $action_name_length = strlen($action);
        $avalilable_size = self::UDP_PACKAGE_MAX_LENGTH - self::PACKAGE_HEADER_LENGTH - $project_name_length - $type_name_length - $action_name_length;
        if(strlen($msg) > $avalilable_size) {
            $msg = substr($msg, 0, $avalilable_size);
        }
        $send_buffer = bin2hex($project.$type.$action.$msg);
        //打包
        return pack('CCCfCNnN', $project_name_length, $type_name_length, $action_name_length, $cost_time, $success, $code, strlen($send_buffer), time()).$send_buffer;
    }

    /**
     * 解包
     * @param string $recv_buffer
     * @return array
     */
    public static function decode(string $recv_buffer)
    {
        $data = unpack('Cproject_name_length/Ctype_name_length/Caction_name_length/fcost_time/Csuccess/Ncode/nmsg_len/Ntime', $recv_buffer);
        $buffer = hex2bin(substr($recv_buffer, self::PACKAGE_HEADER_LENGTH));
        $project = substr($buffer, 0, $data['project_name_length']);
        $type = substr($buffer, $data['project_name_length'], $data['type_name_length']);
        $action = substr($buffer, $data['project_name_length'] + $data['type_name_length'], $data['action_name_length']);
        $msg = substr($buffer, $data['project_name_length'] + $data['type_name_length'] + $data['action_name_length']);

        return [
            'project'   => $project,
            'type'      => $type,
            'action'    => $action,
            'cost_time' => $data['cost_time'],
            'success'   => $data['success'],
            'time'      => $data['time'],
            'code'      => $data['code'],
            'msg'       => $msg
        ];
    }
}
