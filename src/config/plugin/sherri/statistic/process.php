<?php

use Sherri\Statistic\StatisticWorker;

return [
    'StatisticWorker' => [
        'handler'     => StatisticWorker::class,
        'listen'      => '\Sherri\Statistic\Protocols\Statistic://127.0.0.1:55656',
        'transport'   => 'udp',
        'count'       => 1,  // Must be 1
        'constructor' => [
            'config' => [
                'onWorkerStart'    => function($worker) {
                }
            ]
        ]
    ],
];
