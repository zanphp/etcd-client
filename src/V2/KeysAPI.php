<?php

namespace ZanPHP\Component\EtcdClient\V2;

use Zan\Framework\Network\Common\Exception\UnexpectedResponseException;
use Zan\Framework\Network\Common\HttpClient;
use Zan\Framework\Utilities\Types\Json;


/**
 * Class KeysAPI
 * @package ZanPHP\Component\EtcdClient
 *
 * 参考 https://coreos.com/etcd/docs/latest/v2/api.html 实现
 * opts参数为兼容以后可能使用etcd3-api抽象接口
 */
class KeysAPI
{
    private $prefix;

    private $client;

    const V2_PREFIX = "/v2/keys";

    /**
     * KeysAPI constructor.
     * @param EtcdClient $client
     * @param string $prefix baseUrl
     */
    public function __construct(EtcdClient $client, $prefix = "")
    {
        $prefix = trim($prefix, "/");
        $v2 = static::V2_PREFIX;
        $this->prefix = rtrim("$v2/$prefix", "/");
        $this->client = $client;
    }

    private function buildKey($key)
    {
        $prefix = trim($key, "/");
        return rtrim("{$this->prefix}/{$prefix}", "/");
    }

    /**
     * @var $response \Zan\Framework\Network\Common\Response $response
     * @return Error|Response
     * @throws UnexpectedResponseException
     */
    private function parseResponse($response)
    {
        $raw = $response->getBody();

        $json = null;
        try {
            $json = Json::decode($raw, true);
        }
        catch (\Throwable $e) {}
        catch (\Exception $e) {}

        if (isset($e) || !is_array($json)) {
            throw new UnexpectedResponseException("unexpected etcdv2 response $raw");
        }

        if (isset($json["action"])) {
            return new Response($json);
        } elseif (isset($json["errorCode"]) || isset($json["error"])) {
            return new Error($json);
        } else {
            throw new UnexpectedResponseException("unexpected etcdv2 response $raw");
        }
    }

    /**
     * @param $key
     * @param array $opts
     * {
     *
     * Recursive defines whether or not all children of the Node
     * should be returned.
     * Recursive bool
     *
     * Sort instructs the server whether or not to sort the Nodes.
     * If true, the Nodes are sorted alphabetically by key in
     * ascending order (A to z). If false (default), the Nodes will
     * not be sorted and the ordering used should not be considered
     * predictable.
     * Sort bool
     *
     * Quorum specifies whether it gets the latest committed value that
     * has been applied in quorum of members, which ensures external
     * consistency (or linearizability).
     * Quorum bool
     *
     * }
     * @return \Generator
     */
    public function get($key, array $opts = [])
    {
        $opts += [
            "recursive" => false,
            "sort" => false, // 配合顺序创建使用
            "quorum" => false,
            "timeout" => 3000,
        ];

        $params = [];

        if ($opts["recursive"]) {
            $params["recursive"] = true;
        }
        if ($opts["sort"]) {
            $params["sort"] = true;
        }
        if ($opts["quorum"]) {
            $params["quorum"] = true;
        }

        list($host, $port) = $this->client->selectEndpoint();
        $path = $this->buildKey($key);

        $httpClient = new HttpClient($host, $port);
        $response = (yield $httpClient->get($path, $params, $opts["timeout"]));

        yield $this->parseResponse($response);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param array $opts
     * {
     * PrevValue specifies what the current value of the Node must
     * be in order for the Set operation to succeed.
     *
     * Leaving this field empty means that the caller wishes to
     * ignore the current value of the Node. This cannot be used
     * to compare the Node's current value to an empty string.
     *
     * PrevValue is ignored if Dir=true
     * PrevValue string
     *
     * PrevIndex indicates what the current ModifiedIndex of the
     * Node must be in order for the Set operation to succeed.
     *
     * If PrevIndex is set to 0 (default), no comparison is made.
     * PrevIndex uint64
     *
     * PrevExist specifies whether the Node must currently exist
     * (PrevExist) or not (PrevNoExist). If the caller does not
     * care about existence, set PrevExist to PrevIgnore, or simply
     * leave it unset.
     * PrevExist PrevExistType true, false ,null
     *
     * TTL defines a period of time after-which the Node should
     * expire and no longer exist. Values <= 0 are ignored. Given
     * that the zero-value is ignored, TTL cannot be used to set
     * a TTL of 0.
     * TTL int ms
     *
     * Refresh set to true means a TTL value can be updated
     * without firing a watch or changing the node value. A
     * value must not be provided when refreshing a key.
     * Refresh bool
     *
     * Dir specifies whether or not this Node should be created as a directory.
     * Dir bool
     *
     * NoValueOnSuccess specifies whether the response contains the current value of the Node.
     * If set, the response will only contain the current value when the request fails.
     * NoValueOnSuccess bool
     * }
     * @param string $method
     * @return \Generator
     */
    public function set($key, $value, array $opts = [], $method = "PUT")
    {
        $opts += [
            "prevValue" => null, // 配合处理cas
            "prevIndex" => 0, // 配合处理cas
            "prevExist" => null, // true, false, null 存在 不存在 忽略
            "ttl" => 0,
            "refresh" => false,
            "dir" => false,
            "noValueOnSuccess" => false,
            "timeout" => 3000,
        ];

        $params = [];

        if ($opts["ttl"] > 0) {
            $params["ttl"] = $opts["ttl"];
        }
        if (isset($opts["prevValue"])) {
            $params["prevValue"] = $opts["prevExist"];
        }
        if ($opts["prevIndex"] > 0) {
            $params["prevIndex"] = $opts["prevIndex"];
        }
        if (isset($opts["prevExist"])) {
            $params["prevExist"] = $opts["prevExist"];
        }
        if ($opts["dir"]) {
            $params["dir"] = true;
        }
        if ($opts["refresh"]) {
            $params["refresh"] = true;
        }
        if ($opts["noValueOnSuccess"]) {
            // Specifying noValueOnSuccess option skips returning the node as value.
            // 只返回action, 不返回node
            $params["noValueOnSuccess"] = true;
        }
        if ($value !== null) {
            $params["value"] = JSON::encode($value);
        }

        list($host, $port) = $this->client->selectEndpoint();
        $path = $this->buildKey($key);

        $httpClient = new HttpClient($host, $port);
        $httpClient->setMethod("PUT");
        $httpClient->setTimeout($opts["timeout"]);
        $httpClient->setUri($path);
        $httpClient->setBody(http_build_query($params));
        $httpClient->setHeader([
            'Content-Type' => 'application/x-www-form-urlencoded'
        ]);

        $response = (yield $httpClient->build());

        yield $this->parseResponse($response);
    }

    /**
     * Create is an alias for Set w/ PrevExist=false
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @param array $opts
     * {
     * TTL defines a period of time after-which the Node should
     * expire and no longer exist. Values <= 0 are ignored. Given
     * that the zero-value is ignored, TTL cannot be used to set
     * a TTL of 0.
     * TTL int ms
     * }
     * @return \Generator
     */
    public function create($key, $value, $ttl = 0, array $opts = [])
    {
        $opts["ttl"] = $ttl;
        $opts["prevExist"] = false;
        yield $this->set($key, $value, $opts);
    }

    /**
     * @param string $dir 必须为目录
     * @param mixed $value
     * @param int $ttl
     * @param array $opts
     * {
     * TTL defines a period of time after-which the Node should
     * expire and no longer exist. Values <= 0 are ignored. Given
     * that the zero-value is ignored, TTL cannot be used to set
     * a TTL of 0.
     * TTL int ms
     * }
     * @return \Generator
     */
    public function createInOrder($dir, $value, $ttl = 0, array $opts = [])
    {
        $opts["ttl"] = $ttl;
        yield $this->set($dir, $value, $opts, "POST");
    }

    public function createHiddenNode($key, $value, $ttl = 0, array $opts = [])
    {
        $key = "_$key";
        yield $this->create($key, $value, $ttl, $opts);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @param array $opts
     * @return \Generator
     */
    public function update($key, $value, $ttl = 0, array $opts = [])
    {
        $opts["ttl"] = $ttl;
        $opts["prevExist"] = true;
        yield $this->set($key, $value, $opts);
    }

    public function refresh($key, $ttl, array $opts = [])
    {
        $opts["ttl"] = $ttl;
        $opts["refresh"] = true;
        $opts["prevExist"] = true;
        yield $this->set($key, null, $opts);
    }

    /**
     * @param string $key
     * @param array $opts
     * {
     * PrevValue specifies what the current value of the Node must
     * be in order for the Delete operation to succeed.
     *
     * Leaving this field empty means that the caller wishes to
     * ignore the current value of the Node. This cannot be used
     * to compare the Node's current value to an empty string.
     * PrevValue string
     *
     * PrevIndex indicates what the current ModifiedIndex of the
     * Node must be in order for the Delete operation to succeed.
     *
     * If PrevIndex is set to 0 (default), no comparison is made.
     * PrevIndex uint64
     *
     * Recursive defines whether or not all children of the Node
     * should be deleted. If set to true, all children of the Node
     * identified by the given key will be deleted. If left unset
     * or explicitly set to false, only a single Node will be
     * deleted.
     * Recursive bool
     *
     * Dir specifies whether or not this Node should be removed as a directory.
     * Dir bool
     * }
     * @return \Generator
     */
    public function delete($key, array $opts = [])
    {
        $opts += [
            "prevValue" => "", // 配合处理cas delete
            "prevIndex" => 0, // 配合处理cas delete
            "recursive" => false,
            "dir" => false,
            "timeout" => 3000,
        ];

        $params = [];

        if (strlen($opts["prevValue"])) {
            $params["prevValue"] = $opts["prevValue"];
        }
        if ($opts["prevIndex"] > 0) {
            $params["prevIndex"] = $opts["prevIndex"];
        }
        if ($opts["recursive"]) {
            $params["recursive"] = true;
        }
        if ($opts["dir"]) {
            $params["dir"] = true;
        }

        list($host, $port) = $this->client->selectEndpoint();
        $path = $this->buildKey($key);

        $httpClient = new HttpClient($host, $port);
        $httpClient->setMethod("DELETE");
        $httpClient->setTimeout($opts["timeout"]);
        $httpClient->setUri($path);
        $httpClient->setBody(http_build_query($params));
        $httpClient->setHeader([
            'Content-Type' => 'application/x-www-form-urlencoded'
        ]);

        $response = (yield $httpClient->build());

        yield $this->parseResponse($response);
    }

    public function dirTTL($dir, $ttl, array $opts = [])
    {
        $opts["dir"] = true;
        $opts["ttl"] = $ttl;
        yield $this->set($dir, null, $opts);
    }

    /**
     * Note that CompareAndSwap does not work with directories.
     * If an attempt is made to CompareAndSwap a directory,
     * a 102 "Not a file" error will be returned.
     * @param string $key
     * @param mixed $oldVal
     * @param mixed $newVal
     * @param array $opts
     * @return \Generator
     */
    public function casSetWithValue($key, $newVal, $oldVal, array $opts = [])
    {
        $opts["prevValue"] = $oldVal;
        $opts["prevExist"] = true;
        yield $this->set($key, $newVal, $opts);
    }

    public function casSetWithIndex($key, $newVal, $oldIndex, array $opts = [])
    {
        $opts["prevIndex"] = $oldIndex;
        $opts["prevExist"] = true;
        yield $this->set($key, $newVal, $opts);
    }

    public function casDeleteWithValue($key, $oldVal, array $opts = [])
    {
        $opts["prevValue"] = $oldVal;
        yield $this->delete($key, $opts);
    }

    public function casDeleteWithIndex($key, $oldIndex, array $opts = [])
    {
        $opts["prevIndex"] = $oldIndex;
        yield $this->delete($key, $opts);
    }

    public function createDir($dir, $ttl = 0, array $opts = [])
    {
        $opts["dir"] = true;
        yield $this->create($dir, null, $ttl, $opts);
    }

    public function listDir($dir, $recursive, array $opts = [])
    {
        $opts["recursive"] = $recursive;
        yield $this->get($dir, $opts);
    }

    /**
     * To delete a directory that holds keys, you must add recursive=true.
     *
     * @param $dir
     * @param $recursive
     * @param array $opts
     * @return \Generator
     */
    public function deleteDir($dir, $recursive, array $opts = [])
    {
        $opts["recursive"] = $recursive;
        $opts["dir"] = true;
        yield $this->delete($dir, $opts);
    }

    /**
     * Note: etcd only keeps the responses of the most recent 1000 events
     * across all etcd keys. It is recommended to send the response to
     * another thread to process immediately instead of blocking
     * the watch while processing the result.
     *
     * @param string $key
     * @param array $opts
     * {
     * AfterIndex defines the index after-which the Watcher should
     * start emitting events. For example, if a value of 5 is
     * provided, the first event will have an index >= 6.
     *
     * Setting AfterIndex to 0 (default) means that the Watcher
     * should start watching for events starting at the current
     * index, whatever that may be.
     * WaitIndex uint64
     *
     * Recursive specifies whether or not the Watcher should emit
     * events that occur in children of the given keyspace. If set
     * to false (default), events will be limited to those that
     * occur for the exact key.
     * Recursive bool
     * }
     * @return \Generator
     */
    public function watch($key, array $opts = [])
    {
        $opts += [
            "waitIndex" => 0,
            "recursive" => true,
            "timeout" => 30000, // http long pooling
        ];

        $params = ["wait" => true];

        if ($opts["waitIndex"] > 0) {
            $params["waitIndex"] = $opts["waitIndex"];
        }

        if ($opts["recursive"]) {
            $params["recursive"] = true;
        }

        list($host, $port) = $this->client->selectEndpoint();
        $path = $this->buildKey($key);

        $httpClient = new HttpClient($host, $port);
        $response = (yield $httpClient->get($path, $params, $opts["timeout"]));

        yield $this->parseResponse($response);
    }

    /**
     * Discover looks up the etcd servers for the domain.
     * @param string $domain
     */
    public function discover($domain)
    {
        throw new \BadMethodCallException("not support still");
    }
}