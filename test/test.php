<?php

namespace ZanPHP\Component\EtcdClient\V2;

use Zan\Framework\Foundation\Coroutine\Task;

require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../../../../vendor/autoload.php";

call_user_func(function() {

    $endpoints = [
        [
            "host" => "etcd0-qa.s.qima-inc.com",
            "port" => 2379,
        ],
        [
            "host" => "etcd1-qa.s.qima-inc.com",
            "port" => 2379,
        ],
        [
            "host" => "etcd2-qa.s.qima-inc.com",
            "port" => 2379,
        ],
    ];

    $etcdClient = new EtcdClient($endpoints);
    $keysAPI = $etcdClient->keysAPI();

    $testTask = function() use($keysAPI) {
        $resp = (yield $keysAPI->get());
    };


    Task::execute($testTask());
});