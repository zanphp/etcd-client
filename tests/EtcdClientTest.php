<?php
/**
 * Created by PhpStorm.
 * User: huye
 * Date: 2017/9/25
 * Time: 下午3:25
 */

namespace ZanPHP\EtcdClient\Tests;

use ZanPHP\EtcdClient\V2\EtcdClient;
use ZanPHP\EtcdClient\V2\KeysAPI;
use ZanPHP\EtcdClient\V2\Response;
use ZanPHP\EtcdClient\V2\Error;
use ZanPHP\EtcdClient\V2\APCuSubscriber;
use ZanPHP\EtcdClient\V2\Watcher;

use ZanPHP\Testing\TaskTest;

class EtcdClientTest extends TaskTest
{
    /**
     * @var \ZanPHP\EtcdClient\V2\EtcdClient
     */
    private $etcdClient;
    /**
     * @var \ZanPHP\EtcdClient\V2\KeysAPI
     */
    private $keysAPI;

    private $testEndpoints = array(
        [
            "host" => "etcd-dev.s.qima-inc.com",
            "port" => 2379,
        ],
        [
            "host" => "etcd-dev.s.qima-inc.com",
            "port" => 2379,
        ],
    );
    protected function setUp(){
        parent::setUp();
        $this->etcdClient = new EtcdClient([
            "endpoints" => $this->testEndpoints,
            "timeout" => 1000,
        ]);
        $prefix = "/service_chain/test";
        $this->keysAPI = $this->etcdClient->keysAPI($prefix);

    }

    protected function tearDown(){
        parent::tearDown();

    }


    public function taskKeysAPISet(){
        $resp = (yield $this->keysAPI->set("/a/b/c1", 'a_b_c1'));
        $this->assertInstanceOf(Response::class,$resp,'keysAPI set failed');
        $this->assertEquals('a_b_c1',$resp->node->value,'keysAPI set failed');
        $resp = (yield $this->keysAPI->set("/a/b/c2", 'a_b_c2'));
        $this->assertInstanceOf(Response::class,$resp,'keysAPI set failed');
        $this->assertEquals('a_b_c2',$resp->node->value,'keysAPI set failed');
    }

    public function taskKeysAPIGet(){
        $resp = (yield $this->keysAPI->get("/a/b/c1"));
        $this->assertInstanceOf(Response::class,$resp,'keysAPI get failed');
        $this->assertEquals('a_b_c1',$resp->node->value,'keysAPI get failed');

    }

    public function taskKeysAPIUpdate(){
        $resp = (yield $this->keysAPI->update("/a/b/c1", 'a_b_c1_update'));
        $this->assertInstanceOf(Response::class,$resp,'keysAPI update failed');
        $resp = (yield $this->keysAPI->get("/a/b/c1"));
        $this->assertInstanceOf(Response::class,$resp,'keysAPI update failed');
        $this->assertEquals('a_b_c1_update',$resp->node->value,'keysAPI update failed');
    }

    public function taskKeysAPIRefreshTTL(){
        $resp = (yield $this->keysAPI->refreshTTL("/a/b/c1", 1));
        $this->assertInstanceOf(Response::class,$resp,'keysAPI refreshTTL failed');
        sleep(2);
        $error = (yield $this->keysAPI->get("/a/b/c1"));
        $this->assertInstanceOf(Error::class,$error,'keysAPI refreshTTL failed');
        $this->assertTrue($error->isKeyNotFound(),'keysAPI refreshTTL failed');
    }

    public function taskKeysAPIDelete(){
        $resp = (yield $this->keysAPI->delete("/a/b/c2"));
        $this->assertInstanceOf(Response::class,$resp,'keysAPI delete failed');
        $error = (yield $this->keysAPI->get("/a/b/c2"));
        $this->assertInstanceOf(Error::class,$error,'keysAPI delete failed');
        $this->assertTrue($error->isKeyNotFound(),'keysAPI delete failed');
    }

    public function taskKeysAPIDeleteDir(){
        $resp = (yield $this->keysAPI->deleteDir("/a"));
        $this->assertInstanceOf(Response::class,$resp,'keysAPI deleteDir failed');
        $error = (yield $this->keysAPI->get("/a"));
        $this->assertInstanceOf(Error::class,$error,'keysAPI deleteDir failed');
        $this->assertTrue($error->isKeyNotFound(),'keysAPI deleteDir failed');
    }


//    public function taskAPI(){
//        //$resp = (yield $this->keysAPI->set("/a/", 'HY'));
//        //$resp = (yield $this->keysAPI->dirTTL("/a/b", 10));
//        //$resp = (yield $this->keysAPI->set("/a", 'HY1'));
//        $resp = (yield $this->keysAPI->watchOnce("/a",array("waitIndex"=>"440478453")));
//        print_r($resp);
//    }


}