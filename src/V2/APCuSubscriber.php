<?php

namespace ZanPHP\Component\EtcdClient\V2;


use ZanPHP\Component\Cache\APCuStore;
use ZanPHP\Contracts\Cache\Store;

class APCuSubscriber implements Subscriber
{
    /**
     * @var Store
     */
    private $store;

    private $subscriber;

    public function __construct(callable $subscriber)
    {
        $this->subscriber = $subscriber;
        // TODO refactor to DI
        $this->store = new APCuStore(spl_object_hash($this));
    }

    public function getCurrentIndex()
    {
        return $this->store->get("waitIndex");
    }

    public function updateWaitIndex($index)
    {
        $this->store->forever("waitIndex", $index);
    }

    /**
     * @param Watcher $watcher
     * @param Response|Error $response
     * @return void
     */
    public function onChange(Watcher $watcher, $response)
    {
        $subs = $this->subscriber;
        $subs($watcher, $response);
    }
}