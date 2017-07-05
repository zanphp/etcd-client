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
     * @param Watcher $watcher
     * @param Response|Error $response 全量更新 response 返回get结果, 增量更新 watch 返回值
     * @return void
     */
    public function onChange(Watcher $watcher, $response);
}