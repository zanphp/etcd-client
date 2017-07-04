<?php

namespace ZanPHP\Component\EtcdClient\V2;


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

    private $opts;

    public function __construct($key, KeysAPI $keysAPI, Subscriber $subscriber)
    {
        $this->index = 0;
        $this->key = $key;
        $this->keysApi = $keysAPI;
        $this->subscriber = $subscriber;
    }

    public function watch(array $opts = [])
    {
        $this->opts = $opts;
        $this->running = true;
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
     * @param array $opts
     * setOpts(["timeout" => int]) ms
     * setOpts(["recursive" => bool])
     * ...
     */
    public function setOpts(array $opts)
    {
        $this->opts = array_merge($this->opts, $opts);
    }

    private function doWatch()
    {
        while ($this->running) {
            try {
                $waitIndex = $this->subscriber->getCurrentIndex();
                if ($waitIndex) {
                    $this->opts["waitIndex"] = $waitIndex;
                } else {
                    unset($this->opts["waitIndex"]);
                }

                /** @var Response $response */
                $response = (yield $this->keysApi->watchOnce($this->key, $this->opts));

                $this->updateWaitIndex($waitIndex, $response);

                $this->subscriber->onChange($this, $response);

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

    private function updateWaitIndex($currentIndex, $response)
    {
        /** @var $response Response|Error */
        if ($response instanceof Error) {
            if ($response->index) {
                $this->subscriber->updateWaitIndex($response->index + 1);
            }
        } else if ($response instanceof Response) {
            $indexList = [ $currentIndex, $response->index ];

            if ($node = $response->node) {
                $indexList[] = $node->modifiedIndex;
            }
            if ($prevNode = $response->prevNode) {
                $indexList[] = $prevNode->modifiedIndex;
            }

            $this->subscriber->updateWaitIndex(max(...$indexList) + 1);
        }
    }
}