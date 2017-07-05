<?php

namespace ZanPHP\Component\EtcdClient\V2;


use Kdt\Iron\NSQ\Foundation\Timer;
use Zan\Framework\Foundation\Coroutine\Task;
use Zan\Framework\Network\Common\Exception\HttpClientTimeoutException;

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
     * @param array $watchOpts
     * @param bool $fullUpdate 全量 or 增量
     * @param array $getOpts 全量方式请求参数
     */
    public function watch(array $watchOpts = [],
                          $fullUpdate = true,
                          array $getOpts = ["recursive" => true])
    {
        $this->getOpts = $getOpts;
        $this->running = false;
        $this->watchOpts = $watchOpts;
        $this->isFullUpdate = $fullUpdate;
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
            $resp = (yield $this->keysApi->get($this->key));
            $currentIndex = $resp->header->etcdIndex;
            $this->subscriber->updateWaitIndex($currentIndex);
            $this->running = true;
        } catch (\Throwable $e) {
        } catch (\Exception $e) {}

        if (isset($e)) {
            Timer::after(1000, function() {
                $this->watch($this->watchOpts);
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
                    sys_error("service chain etcd watch error: " . $error);
                    // 401 错误, 重新拉取后watch
                    if ($error->isEventIndexCleared()) {
                        $this->watch($this->watchOpts);
                        return;
                    }
                }

                if ($this->isFullUpdate) {
                    $getResp = (yield $this->keysApi->get($this->key, $this->getOpts)); // retry ~
                    $nextIndex = $getResp->header->etcdIndex;
                    $nextResp = $getResp;
                } else {
                    $nextIndex = $this->getNextWaitIndex($watchResp);
                    $nextResp = $watchResp;
                }

                $this->subscriber->updateWaitIndex($nextIndex + 1);
                $this->subscriber->onChange($this, $nextResp);

            } catch (HttpClientTimeoutException $e) {
                yield taskSleep(10);

            } catch (\Throwable $t) {
                echo_exception($t);
                yield taskSleep(50);

            } catch (\Exception $ex) {
                echo_exception($ex);
                yield taskSleep(50);
            }
        }
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