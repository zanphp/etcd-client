<?php

namespace ZanPHP\Component\EtcdClient\V2;


class LocalSubscriber implements Subscriber
{

    /**
     * @var int
     */
    private $waitIndex;

    private $subscriber;

    public function __construct(callable $subscriber)
    {
        $this->subscriber = $subscriber;
    }

    public function getCurrentIndex()
    {
        return $this->waitIndex;
    }

    public function updateWaitIndex($index)
    {
        $this->waitIndex = $index;
    }

    /**
     * @param Error|Response $response
     * @return mixed|void
     */
    public function onChange($response)
    {
        $subs = $this->subscriber;
        $subs($response);
    }
}