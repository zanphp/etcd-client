<?php

namespace ZanPHP\Component\EtcdClient\V2;


class Response
{

    /**
     * @var string
     */
    public $action;

    /**
     * @var Node
     */
    public $node;

    /**
     * @var Node
     */
    public $prevNode;

    public $index;

    public $clusterId; // TODO from header

    /**
     * Response constructor.
     * @param array $json
     */
    public function __construct(array $json)
    {
        if (isset($json["action"])) {
            $this->action = $json["action"];
        }

        if (isset($json["node"]) && is_array($json["node"])) {
            $this->node = new Node($json["node"]);
        }

        if (isset($json["prevNode"]) && is_array($json["prevNode"])) {
            $this->prevNode = new Node($json["prevNode"]);
        }

        if (isset($json["index"])) {
            $this->index = intval($json["index"]);
        }
    }
}