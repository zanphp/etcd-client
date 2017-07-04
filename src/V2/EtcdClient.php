<?php

namespace ZanPHP\Component\EtcdClient\V2;



class EtcdClient
{
    const EndpointSelectionRandom = 1;

    private $endpoints = [];

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
        $this->selectionMode = $selectionMode;
        foreach ($endpoints as $endpoint) {
            if (isset($endpoint["host"]) && isset($endpoint["port"])) {
                $this->endpoints[] = [$endpoint["host"], $endpoint["port"]];
            }
        }
        if (empty($this->endpoints)) {
            throw new \InvalidArgumentException("empty etcd endpoints in etcd clientV2");
        }
    }

    public function keysAPI($prefix = "")
    {
        return new KeysAPI($this, $prefix);
    }

    public function selectEndpoint()
    {
        if ($this->selectionMode === static::EndpointSelectionRandom) {
            return $this->endpoints[array_rand($this->endpoints)];
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