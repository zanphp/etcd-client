<?php

namespace ZanPHP\EtcdClient\V2;

use Zan\Framework\Foundation\Coroutine\Task;
use Zan\Framework\Network\Common\Exception\HttpClientTimeoutException;


require "/Users/chuxiaofeng/yz_env/webroot/zan-com/tcp-demo/vendor/autoload.php";
require __DIR__ . "/../vendor/autoload.php";

call_user_func(function() {

    $qaEndpoints = [
        [
            "host" => "127.0.0.1",
            "port" => 2379,
        ],
        [
            "host" => "127.0.0.1",
            "port" => 2379,
        ],
        [
            "host" => "127.0.0.1",
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

    $etcdClient = new EtcdClient([
        "endpoints" => $testEndpoints,
        "timeout" => 1000,
    ]);
    $prefix = "/service_chain/app_to_chain_nodes";
    $keysAPI = $etcdClient->keysAPI($prefix);

    $clear = function() use($keysAPI) {
        $resp = (yield $keysAPI->delete("/a", [
            "dir" => true,
            "recursive" => true,
        ]));
        print_r($resp);

        swoole_event_exit();
    };

    $get = function() use($keysAPI) {
        $resp = (yield $keysAPI->get("/"));
        print_r($resp);

        swoole_event_exit();
    };

    $getRecursive = function() use($keysAPI) {
        $resp = (yield $keysAPI->get("/", [
            "recursive" => true,
        ]));
        print_r($resp);

        swoole_event_exit();
    };

    $getNotFound = function() use($keysAPI) {
        $resp = (yield $keysAPI->get("/not_exist"));
        print_r($resp);

        assert($resp instanceof Error);
        $error = $resp;
        assert($error->isKeyNotFound());

        swoole_event_exit();
    };

    $set = function() use($keysAPI) {
        // 非目录

        $resp = (yield $keysAPI->set("/a/b/c", null));
        print_r($resp);
        assert($resp instanceof Response);
        assert($resp->node instanceof Node);
        assert($resp->node->dir === false);

        $resp = (yield $keysAPI->delete("/a/b/c"));
        print_r($resp);

        swoole_event_exit();
    };

    $setValue = function() use($keysAPI) {
        $resp = (yield $keysAPI->set("/a/b/c", "hi"));
        print_r($resp);
        assert($resp instanceof Response);
        assert($resp->node instanceof Node);
        assert($resp->node->dir === false);
        assert($resp->node->value === "hi");

        $resp = (yield $keysAPI->delete("/a/b/c"));
        print_r($resp);

        swoole_event_exit();
    };

    $setTTL = function() use($keysAPI) {
        $resp = (yield $keysAPI->set("/a/b/c", "hi", [
            "ttl" => 3,
        ]));
        print_r($resp);
        yield taskSleep(2000);
        $resp = (yield $keysAPI->get("/a/b/c"));
        print_r($resp);

        $resp = (yield $keysAPI->delete("/a/b/c"));
        print_r($resp);

        swoole_event_exit();
    };

    $refreshTTL = function() use($keysAPI) {
        $resp = (yield $keysAPI->set("/a/b/c", "hi", [
            "ttl" => 3,
        ]));

        print_r($resp);
        yield taskSleep(2000);

        $resp = (yield $keysAPI->get("/a/b/c"));
        print_r($resp);

        $resp = (yield $keysAPI->refreshTTL("/a/b/c", 10));
        print_r($resp);

        $resp = (yield $keysAPI->get("/a/b/c"));
        print_r($resp);

        $resp = (yield $keysAPI->delete("/a/b/c"));
        print_r($resp);

        swoole_event_exit();
    };

    $setDelDir = function() use($keysAPI) {
        // 已经存在的文件夹, 再次创建会出错 not is a file
        $resp = (yield $keysAPI->set("/a/b/d", null, ["dir" => true]));
        print_r($resp);
        assert($resp instanceof Response);
        assert($resp->node instanceof Node);
        assert($resp->node->dir === true);

        $resp = (yield $keysAPI->delete("/a/b/d", [
            "dir" => true,
        ]));
        print_r($resp);
        assert($resp instanceof Response);

        swoole_event_exit();
    };

    $delRecursive = function() use($keysAPI) {
        $resp = (yield $keysAPI->set("/a/b/e", null, ["dir" => true]));
        print_r($resp);
        $resp = (yield $keysAPI->set("/a/b/e/a", "hi"));
        print_r($resp);
        $resp = (yield $keysAPI->delete("/a/b/e", [
            "dir" => true,
            // 目录下有值 必须递归删除
            "recursive" => true,
        ]));
        print_r($resp);

        swoole_event_exit();
    };


    $watchOnce = function() use($keysAPI, $clear) {
        $resp = (yield $keysAPI->set("/a/b/c", null));
        print_r($resp);

        try {
            $resp = (yield $keysAPI->watchOnce("/a/b/c", [
                "timeout" => 2000,
            ]));
            print_r($resp);
        } catch (HttpClientTimeoutException $ex) {
            echo $ex;
        }

        yield $clear();

        swoole_event_exit();
    };

    // watch 不存在的key 不会发生什么
    $watchNotFoundKey = function() use($keysAPI, $clear) {
        $resp = (yield $keysAPI->delete("/a/b/not_found", [ "recursive" => true ]));
        print_r($resp);

        try {
            $resp = (yield $keysAPI->watchOnce("/a/b/not_found", [ "timeout" => 2000, ]));
            print_r($resp);
        } catch (HttpClientTimeoutException $ex) {
            echo $ex;
        }

        yield $clear();
    };

    $create = function() use($keysAPI, $clear) {
        $resp = (yield $keysAPI->create("/a/b/c", "hi"));
        print_r($resp);


        // Key already exists
        $resp = (yield $keysAPI->create("/a/b/c", "hi"));
        // Key already exists
        assert($resp instanceof Error);

        yield $clear();
    };

    $createInOrder = function() use($keysAPI, $clear) {
        $resp = (yield $keysAPI->createInOrder("/a/b/c", "hi"));
        print_r($resp);
        $resp = (yield $keysAPI->createInOrder("/a/b/c", "hello"));
        print_r($resp);

        $resp = (yield $keysAPI->createInOrder("/a/b/c", "hello"));
        print_r($resp);

        yield $clear();
    };

    $createHidden = function() use($keysAPI, $clear) {
        $resp = (yield $keysAPI->createHiddenNode("/a/b/hidden", "hi"));
        print_r($resp);

        yield $clear();
    };

    $update = function() use($keysAPI, $clear) {
        $resp = (yield $keysAPI->update("/a/b/not_exist", null));
        print_r($resp);
        // Key not found
        assert($resp instanceof Error);

        yield $clear();
    };

    $watch = function() use($keysAPI, $clear) {
        $i = 0;

        $subscriber = new LocalSubscriber(function(Watcher $watcher, $watchResp) use(&$i) {
            var_dump($watchResp);
            ++$i;
            if ($i > 2) {
                $watcher->stopWatch();
                swoole_timer_tick(1000, function() use($watcher) {
                    if (!$watcher->isWatching()) {
                        swoole_event_exit();
                    }
                });
            }
        });

        $change = function() use($keysAPI, &$change) {
            try {
                yield $keysAPI->set("/a/b/c", rand(1, 10));
                yield taskSleep(1000);

                // ttl refresh 貌似不触发 修改事件
                yield $keysAPI->refreshTTL("/a/b/c", rand(1, 10));
                yield taskSleep(1000);

                yield $keysAPI->set("/a/b/c", rand(1, 10));
                yield taskSleep(1000);

                yield $keysAPI->delete("/a/b/c");
            } catch (\Throwable $e) {

            } catch (\Exception $e) {

            }

            if (isset($e)) {
                yield taskSleep(1000);
                Task::execute($change());
            }
        };

        Task::execute($change());

        $watcher = $keysAPI->watch("/a/b/c", $subscriber);
        $watcher->watch([
            "timeout" => 1500,
        ], false);
    };

    // 全量更新
    $watch2 = function() use($keysAPI, $clear) {
        $subscriber = new LocalSubscriber(function(Watcher $watcher, $getResp) {
            print_r($getResp);
        });

        $change = function() use($keysAPI, &$change) {
            try {
                yield $keysAPI->set("/a/b/c", rand(1, 10));
                yield taskSleep(1000);

                // ttl refresh 貌似不触发 修改事件
                yield $keysAPI->refreshTTL("/a/b/c", rand(1, 10));
                yield taskSleep(1000);

                yield $keysAPI->set("/a/b/c", rand(1, 10));
                yield taskSleep(1000);

                yield taskSleep(1000);
                yield $keysAPI->delete("/a/b/c");
                swoole_event_exit();
            } catch (\Throwable $e) {

            } catch (\Exception $e) {

            }

            if (isset($e)) {
                yield taskSleep(1000);
                Task::execute($change());
            }
        };

        Task::execute($change());

        $watcher = $keysAPI->watch("/a/b/c", $subscriber);
        $watcher->watch([
            "timeout" => 1500,
        ]);
    };

    $keyNotFoundHeaderInfo = function() use($keysAPI) {
        $resp = (yield $keysAPI->get("/a/b/c/not_exist"));
        print_r($resp);

        swoole_event_exit();
    };

    $dirTTL = function() use($keysAPI) {};

    $createDir = function() use($keysAPI) {};

    $listDir = function() use($keysAPI) {};

    $delDir = function() use($keysAPI) {};

    $casSetWithValue = function() use($keysAPI) {};

    $casSetWithIndex = function() use($keysAPI) {};

    $casDeleteWithValue = function() use($keysAPI) {};

    $casDeleteWithIndex = function() use($keysAPI) {};


    Task::execute($watch2());
});