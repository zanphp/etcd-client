<?php

namespace ZanPHP\EtcdClient\V2;


use ZanPHP\Contracts\Cache\ShareMemoryStore;
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
        $this->store = make(ShareMemoryStore::class, spl_object_hash($this));
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