<?php

namespace ZanPHP\EtcdClient\V2;


use ZanPHP\Coroutine\Task;
use ZanPHP\HttpClient\Exception\HttpClientTimeoutException;
use ZanPHP\Timer\Timer;

class Watcher
{
    /**
     * @var string
     */
    private $key;

    private $keysApi;

    private $subscriber;

    private $running;

    private $watchOpts;

    private $getOpts;

    private $isFullUpdate = false;

    public function __construct($key, KeysAPI $keysAPI, Subscriber $subscriber)
    {
        $this->index = 0;
        $this->key = $key;
        $this->keysApi = $keysAPI;
        $this->subscriber = $subscriber;
    }

    /**
     * 全量更新
     * @param array $watchOpts
     * @param array $getOpts 全量方式请求参数
     */
    public function watch(array $watchOpts = [], array $getOpts = [])
    {
        $this->getOpts = $getOpts + ["recursive" => true];
        $this->running = false;
        $this->watchOpts = $watchOpts;
        $this->isFullUpdate = true;

        $task = $this->doWatch();
        Task::execute($task);
    }

    /**
     * 增量更新
     * @param array $watchOpts
     */
    public function watchIncrementally(array $watchOpts = [])
    {
        $this->running = false;
        $this->watchOpts = $watchOpts;
        $this->isFullUpdate = false;

        $task = $this->doWatch();
        Task::execute($task);
    }

    public function stopWatch()
    {
        $this->running = false;
    }

    public function getSubscriber()
    {
        return $this->subscriber;
    }

    public function isWatching()
    {
        return $this->running;
    }

    /**
     * @param array $watchOpts
     * setOpts(["timeout" => int]) ms
     * setOpts(["recursive" => bool])
     * ...
     */
    public function setWatchOpts(array $watchOpts)
    {
        $this->watchOpts = array_merge($this->watchOpts, $watchOpts);
    }

    private function doWatch()
    {
        try {
            /** @var Response|Error $resp */
            $resp = (yield $this->keysApi->get($this->key, $this->getOpts));
            $currentIndex = $resp->header->etcdIndex;
            $this->subscriber->updateWaitIndex($currentIndex);
            $this->running = true;
        } catch (\Throwable $e) {
        } catch (\Exception $e) {}

        if (isset($e)) {
            Timer::after(1000, function() {
                $this->running = false;
                Task::execute($this->doWatch());
            });
        }

        while ($this->running) {
            try {
                $waitIndex = $this->subscriber->getCurrentIndex();

                if ($waitIndex) {
                    $this->watchOpts["waitIndex"] = $waitIndex;
                } else {
                    unset($this->watchOpts["waitIndex"]);
                }

                /** @var Response $watchResp */
                $watchResp = (yield $this->keysApi->watchOnce($this->key, $this->watchOpts));

                if ($watchResp instanceof Error) {
                    $error = $watchResp;
                    // 401 错误, 重新拉取后watch
                    if ($error->isEventIndexCleared()) {
                        $this->running = false;
                        Task::execute($this->doWatch());
                        return;
                    } else {
                        sys_error("etcd watch error: " . $error);
                    }
                }

                yield $this->updateWatchState($watchResp);

            } catch (HttpClientTimeoutException $e) {
                yield taskSleep(50);

            } catch (\Throwable $t) {
                echo_exception($t);
                yield taskSleep(50);

            } catch (\Exception $ex) {
                echo_exception($ex);
                yield taskSleep(50);
            }
        }
    }

    private function updateWatchState($watchResp)
    {
        if ($this->isFullUpdate) {
            $getResp = (yield $this->keysApi->get($this->key, $this->getOpts)); // retry ~
            $nextIndex = max($watchResp->index, $getResp->header->etcdIndex); // get的index一定会被watch的大吧!!!
            $nextResp = $getResp;
        } else {
            $nextIndex = $this->getNextWaitIndex($watchResp);
            $nextResp = $watchResp;
        }

        $this->subscriber->updateWaitIndex($nextIndex + 1);
        $this->subscriber->onChange($this, $nextResp);
    }

    private function getNextWaitIndex($watchResp)
    {
        $currentIndex = $this->subscriber->getCurrentIndex();
        $indexList = [ $currentIndex, $watchResp->index ];

        /** @var $watchResp Response|Error */
        if ($watchResp instanceof Response) {
            if ($node = $watchResp->node) {
                $indexList[] = $node->modifiedIndex;
            }
            if ($prevNode = $watchResp->prevNode) {
                $indexList[] = $prevNode->modifiedIndex;
            }
        }

        return max(...$indexList);
    }
}