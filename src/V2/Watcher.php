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

    public function __construct($key, KeysAPI $keysAPI, Subscriber $subscriber)
    {
        $this->index = 0;
        $this->key = $key;
        $this->keysApi = $keysAPI;
        $this->subscriber = $subscriber;
    }

    public function watch(array $opts = [])
    {
        $task = $this->doWatch($opts);
        Task::execute($task);
    }

    private function doWatch($opts = [])
    {
        while (true) {
            try {
                $opts["waitIndex"] = $this->subscriber->getCurrentIndex();

                /** @var Response $response */
                $response = (yield $this->keysApi->watch($this->key, $opts));

                /** @var $response Response|Error */
                if ($response->index > 0) {
                    $this->subscriber->updateWaitIndex($response->index);
                }

                $this->subscriber->onChange($response);
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
}