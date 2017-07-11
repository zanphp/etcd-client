<?php

namespace ZanPHP\EtcdClient\V2;



class EtcdClient
{
    const EndpointSelectionRandom = 1;

    private $config;

    private $selectionMode;

    /**
     * EtcdClientV2 constructor.
     * @param array $config
     * @param int $selectionMode *
     * }
     * @internal param array $endpoints [* [
     * "timeout" => 3000,
     * "endpoints" => [
     *      [
     *          host =>
     *          port =>
     *      ],
     *      ......
     *  ],
     * ]
     */
    public function __construct(array $config, $selectionMode = self::EndpointSelectionRandom)
    {
        $config += [ "timeout" => 3000 ];
        if (!isset($config["endpoints"]) || !is_array($config["endpoints"])) {
            throw new \InvalidArgumentException("empty etcd endpoints in etcd clientV2");
        }

        $endpoints = [];
        foreach ($config["endpoints"] as $endpoint) {
            if (isset($endpoint["host"]) && isset($endpoint["port"])) {
                $endpoints[] = [$endpoint["host"], $endpoint["port"]];
            }
        }

        if (empty($endpoints)) {
            throw new \InvalidArgumentException("empty etcd endpoints in etcd clientV2");
        }

        $config["endpoints"] = $endpoints;
        $this->config = $config;
        $this->selectionMode = $selectionMode;
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

    public function getDefaultTimeout()
    {
        return $this->config["timeout"];
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