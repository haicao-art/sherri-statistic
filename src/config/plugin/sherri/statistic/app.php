<?php
return [
    'enable' => true,
    'address'   => 'udp://127.0.0.1:55656',      //上报地址
    'type'  => 'file',

    'default'    => [
        'file' => [
            'class' => \Sherri\Statistic\Handler\FileHandler::class,
            'config'    => [
                'statisticDir' => runtime_path() . DIRECTORY_SEPARATOR . 'statistic/statistic/',
                'logDir' => runtime_path() . DIRECTORY_SEPARATOR . 'statistic/log/',
            ],
        ]
    ]
];
