<?php

namespace ZanPHP\Component\EtcdClient\V2;


/**
 * Interface Subscriber
 * @package ZanPHP\Component\EtcdClient\V2
 *
 * waitIndex 进程内存储 或者 apcu共享内存存储
 */
interface Subscriber
{
    public function getCurrentIndex();

    public function updateWaitIndex($index);

    /**
     * notify
     * @param Watcher $watcher
     * @param Response|Error $response
     * @return mixed
     */
    public function onChange(Watcher $watcher, $response);
}