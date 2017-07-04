<?php

namespace ZanPHP\Component\EtcdClient\V2;


class ApcuSubscriber implements Subscriber
{

    public function getCurrentIndex()
    {
        // TODO: Implement getCurrentIndex() method.
    }

    public function updateWaitIndex($index)
    {
        // TODO: Implement updateWaitIndex() method.
    }

    /**
     * @param Watcher $watcher
     * @param Response|Error $response
     * @return void
     */
    public function onChange(Watcher $watcher, $response)
    {
        // TODO: Implement onChange() method.
    }
}