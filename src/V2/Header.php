<?php

namespace ZanPHP\EtcdClient\V2;


class Header
{
    public $clusterId;

    /**
     * @var int
     * 401 错误
     * watch 之前应该先 get一次, 获取etcdIndex
     *
     * To start watch, first we need to fetch the current state of key
     * Unlike watches we use the X-Etcd-Index + 1 of the response as a waitIndex
     * instead of the node's modifiedIndex + 1 for two reasons:
     *
     * 1. The X-Etcd-Index is always greater than or equal to the modifiedIndex
     * when getting a key because X-Etcd-Index is the current etcd index, and the modifiedIndex is the index of an event already stored in etcd.
     *
     * 2. None of the events represented by indexes between modifiedIndex
     * and X-Etcd-Index will be related to the key being fetched.
     * Using the modifiedIndex + 1 is functionally equivalent for subsequent
     * watches, but since it is smaller than the X-Etcd-Index + 1,
     * we may receive a 401 EventIndexCleared error immediately.
     */
    public $etcdIndex;

    public $raftIndex;

    public $raftTerm;

    public function __construct(array $headers)
    {
        $headers = array_change_key_case($headers, CASE_LOWER);

        if (isset($headers["x-etcd-cluster-id"])) {
            $this->clusterId = $headers["x-etcd-cluster-id"];
        }

        if (isset($headers["x-etcd-index"])) {
            $this->etcdIndex = $headers["x-etcd-index"];
        }

        if (isset($headers["x-raft-index"])) {
            $this->raftIndex = $headers["x-raft-index"];
        }

        if (isset($headers["x-raft-term"])) {
            $this->raftTerm = $headers["x-raft-term"];
        }
    }
}