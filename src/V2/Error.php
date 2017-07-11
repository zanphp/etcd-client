<?php

namespace ZanPHP\EtcdClient\V2;


class Error
{
    const ErrorCodeKeyNotFound  = 100;
    const ErrorCodeTestFailed   = 101;
    const ErrorCodeNotFile      = 102;
    const ErrorCodeNotDir       = 104;
    const ErrorCodeNodeExist    = 105;
    const ErrorCodeRootROnly    = 107;
    const ErrorCodeDirNotEmpty  = 108;
    const ErrorCodeUnauthorized = 110;

    const ErrorCodePrevValueRequired = 201;
    const ErrorCodeTTLNaN            = 202;
    const ErrorCodeIndexNaN          = 203;
    const ErrorCodeInvalidField      = 209;
    const ErrorCodeInvalidForm       = 210;

    const ErrorCodeRaftInternal = 300;
    const ErrorCodeLeaderElect  = 301;

    const ErrorCodeWatcherCleared    = 400;
    const ErrorCodeEventIndexCleared = 401;

    /**
     * @var int
     */
    public $errorCode;

    /**
     * @var string
     */
    public $message;

    /**
     * @var string
     */
    public $cause;

    /**
     * @var int
     */
    public $index;

    public $header;

    /**
     * Error constructor.
     * @param Header $header
     * @param array $json
     */
    public function __construct(Header $header, array $json)
    {
        $this->header = $header;

        if (isset($json["errorCode"])) {
            $this->errorCode = intval($json["errorCode"]);
            if (isset($json["message"])) {
                $this->message = $json["message"];
            }
        } elseif (isset($json["error"])) {
            $this->errorCode = intval($json["error"]);
            if (isset($json["msg"])) {
                $this->message = $json["msg"];
            }
        }

        if (isset($json["cause"])) {
            $this->cause = $json["cause"];
        }

        if (isset($json["index"])) {
            $this->index = $json["index"];
        }
    }

    public function isEventIndexCleared()
    {
        return $this->errorCode === static::ErrorCodeEventIndexCleared;
    }

    public function isKeyNotFound()
    {
        return $this->errorCode === static::ErrorCodeKeyNotFound;
    }

    public static function getName($errorCode)
    {
        static $errCode2ConstName = [];

        if ($errCode2ConstName === []) {
            $clazz = new \ReflectionClass(static::class);
            $errCode2ConstName = array_flip($clazz->getConstants());
        }

        if (isset($errCode2ConstName[$errorCode])) {
            return $errCode2ConstName[$errorCode];
        } else {
            return "Unknown Error($errorCode)";
        }
    }

    public function __toString()
    {
        return "etcd response error [code=$this->errorCode, msg=$this->message, cause=$this->cause]";
    }
}