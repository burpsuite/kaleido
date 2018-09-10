<?php

namespace Kaleido\Http;

use LeanCloud\LeanObject;
use LeanCloud\Query;
use LeanCloud\Client;

class LeanCloud 
{
    public static $payload = [];
    public static $masterKey;
    public static $appKey;
    public static $appId;
    public static $apiServer;
    public static $className;

    public static function setMasterKey($masterKey) {
        return \is_string($masterKey)
         ? self::$masterKey = $masterKey : null;
    }

    public static function setAppKey($appKey) {
        return \is_string($appKey)
         ? self::$appKey = $appKey : null;
    }

    public static function setAppId($appId) {
        return \is_string($appId)
         ? self::$appId = $appId : null;
    }

    public static function setClassName($className) {
        return \is_string($className)
         ? self::$className = $className : null;
    }

    public static function setApiServer($apiServer) {
        \is_string($apiServer)
         ? Client::setServerUrl($apiServer) : null;
    }

    public function setRequest(LeanObject $class) :LeanObject {
        return $class->set('request', Encoder::payload(false));
    }

    public function setResponse(LeanObject $class) :LeanObject {
        return $class->set('response', Sender::response(false));
    }

    public static function initialize() {
        Client::initialize(self::$appId,
            self::$appKey, self::$masterKey);
    }

    public function leanObject() :LeanObject {
        return new LeanObject(self::$className);
    }

    public function leanQuery() :Query {
        return new Query(self::$className);
    }

    public static function setPayload($name, $value = null) {
        return !\is_string($name)
            ?: self::$payload[$name] = $value;
    }

    public static function getPayload($name = null) {
        return self::$payload[$name] ?? false;
    }

    public function set(LeanObject $class, $name = null) {
        $class->set($name, self::getPayload($name));
    }

    public function get(Query $class, $name = null) :LeanObject {
        return $class->get($name);
    }

    public function setClass($class) {
        self::setApiServer($class->apiServer);
        self::setAppId($class->appId);
        self::setAppKey($class->appKey);
        self::setClassName($class->className);
        self::setMasterKey($class->masterKey);
    }
}