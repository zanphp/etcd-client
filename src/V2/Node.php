<?php

namespace ZanPHP\EtcdClient\V2;


class Node
{
    /**
     * @var string
     */
    public $key;

    /**
     * @var bool
     */
    public $dir;

    /**
     * @var mixed
     */
    public $value;

    /**
     * @var Node[]
     */
    public $nodes;

    /**
     * @var int
     */
    public $createdIndex;

    /**
     * @var int
     */
    public $modifiedIndex;

    /**
     * @var string
     */
    public $expiration;

    /**
     * @var int|null
     */
    public $ttl;

    public function __construct(array $json)
    {
        if (isset($json["key"])) {
            $this->key = $json["key"];
        }

        if (isset($json["dir"])) {
            $this->dir = boolval($json["dir"]);
        } else {
            $this->dir = false;
        }

        if (isset($json["value"])) {
            $this->value = $json["value"];
        }

        if (isset($json["nodes"]) && is_array($json["nodes"])) {
            $this->nodes = [];
            foreach ($json["nodes"] as $nodeJson) {
                if (is_array($nodeJson)) {
                    $this->nodes[] = new static($nodeJson);
                }
            }
        }

        if (isset($json["createdIndex"])) {
            $this->createdIndex = intval($json["createdIndex"]);
        }

        if (isset($json["modifiedIndex"])) {
            $this->modifiedIndex = intval($json["modifiedIndex"]);
        }

        if (isset($json["expiration"])) {
            $this->expiration = intval($json["expiration"]);
        }

        if (isset($json["ttl"])) {
            $this->ttl = intval($json["ttl"]);
        }
    }
}