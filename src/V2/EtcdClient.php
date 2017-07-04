<?php

namespace ZanPHP\Component\EtcdClient\V2;



class EtcdClient
{
    const EndpointSelectionRandom = 1;

    private $config;

    private $selectionMode;

    /**
     * EtcdClientV2 constructor.
     * @param array $endpoints
     * [
     *      [
     *          host =>
     *          port =>
     *      ],
     *      ......
     *  ],
     * @param int $selectionMode*
     * }
     *
     */
    public function __construct(array $endpoints, $selectionMode = self::EndpointSelectionRandom)
    {
        if (!isset($config["endpoints"]) || empty($config["endpoints"])) {
            throw new \InvalidArgumentException("empty etcd endpoints in etcd clientV2");
        }

        $this->selectionMode = $selectionMode;
        $this->config = $config;

    }

    public function keysAPI($prefix = "")
    {
        return new KeysAPI($this, $prefix);
    }

    public function selectEndpoint()
    {
        if ($this->selectionMode === static::EndpointSelectionRandom) {
            $endpoints = $this->config["endpoints"];
            return $endpoints[array_rand($endpoints)];
        } else {
            throw new \BadMethodCallException("not support");
        }
    }

    public function authAPI()
    {
        throw new \BadMethodCallException("not support still");
    }

    public function membersAPI()
    {
        throw new \BadMethodCallException("not support still");
    }
}