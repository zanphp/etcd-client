<?php

namespace ZanPHP\Component\EtcdClient\V2;

use Zan\Framework\Foundation\Coroutine\Task;


require "/Users/chuxiaofeng/yz_env/webroot/zan-com/tcp-demo/vendor/autoload.php";
require __DIR__ . "/../vendor/autoload.php";

call_user_func(function() {

    $qaEndpoints = [
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

    $testEndpoints = [
        [
            "host" => "etcd-dev.s.qima-inc.com",
            "port" => 2379,
        ],
        [
            "host" => "etcd-dev.s.qima-inc.com",
            "port" => 2379,
        ],
    ];

    $etcdClient = new EtcdClient($testEndpoints);
    $prefix = "/service_chain/app_to_chain_nodes";
    $keysAPI = $etcdClient->keysAPI($prefix);

    $testTask = function() use($keysAPI) {
        $resp = (yield $keysAPI->get("/"));
        print_r($resp);
    };


    Task::execute($testTask());
});