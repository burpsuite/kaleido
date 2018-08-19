<?php

namespace Kaleido\Http;

use Curl\Curl;
use Predis\Client;

class Loader extends Worker
{
    public static $lock = [];
    public $allow = ['dynamic'];
    public $expire = [];
    public $hashName;
    public $hashExpire;
    public $loadType;
    public $loadInfo;
    public $loadData;
    public $loadCache = [];

    /**
     * Loader constructor.
     * @throws \ErrorException
     */
    public function __construct() {
        $this->getEnv('database');
        $this->unpackClass('loadInfo');
        $this->handle();
        $this->lockClass();
    }

    public function __toString() {
        return (string)self::fetch();
    }

    /**
     * @throws \ErrorException
     */
    public function handle() {
        switch ($this->loadType) {
            case 'local':
                $this->loadDatabase();
                break;
            case 'remote':
                $this->loadCache();
                $predis = $this->predis();
                $this->loadRedis($predis);
                $this->complete($predis);
                $this->setConsole();
                break;
        }
    }

    private function unpackClass($name = null) {
        foreach ($this->{$name} as $key => $value) {
            !\in_array($key, get_class_vars($this), true)
                 ?: $this->$key = $value;
        }
    }

    /**
     * @throws \ErrorException
     */
    private function loadDatabase() {
        switch ($this->loadType) {
            case 'local':
                $this->local('fetch', $this->loadData);
                $this->isJson($this->getClass('fetch'));
                break;
            case 'remote':
                $curl = new Curl;
                $curl->get($this->loadData);
                $this->getResponse($curl->response);
                break;  
        }
    }

    public static function fetch() {
        return \is_string(self::$lock['fetch'])
            ? self::$lock['fetch'] : 'error_fetch';
    }

    private function local($setName, $fileName) {
        !\is_file($fileName) ?: $this->setClass($setName, 
            file_get_contents($fileName));
    }

    private function loadCache() {
        !\in_array($this->cacheType(), $this->allow, 
            true) ?: $this->setLoadCache('data', 
        getenv($this->getLoadCache('data')));
    }

    private function cacheType() {
        return $this->loadCache['type'] ?? false;
    }

    private function checkLoadData() {
        if (!$this->getLoadCache('data')) {
            new HttpException(
                self::getError('load_failed'), 500
            );
        }
    }

    private function predis() :Client {
        return new Client($this->getLoadCache('data'));
    }

    private function loadRedis(Client $predis) {
        $this->checkLoadData();
        $this->generateHash();
        $this->isExist($predis);
        $this->isExpired($predis);
    }

    private function getLoadCache($name) {
        return $this->loadCache[$name] ?? false;
    }

    private function setLoadCache($name, $value = null) {
        !\is_string($name) && !$this->loadCache[$name]
            ?: $this->loadCache[$name] = $value;
    }

    /**
     * @param $taskId
     * @param $url
     * @return string
     * @throws \ErrorException
     * @throws \LeanCloud\CloudException
     */
    public function listenHttp($taskId, $url) :string {
        new Encoder($taskId, $url);
        new Sender(Encoder::class(false));
        new Decoder(Sender::response(false));
        new Capture();
        return Decoder::getBody();
    }

    /**
     * @param $activity
     * @param $objectId
     * @return string
     * @throws \ErrorException
     * @throws \LeanCloud\CloudException
     */
    public function replayHttp($activity, $objectId) :string {
        new Capture($activity, $objectId);
        return Decoder::getBody();
    }

    /**
     * @param Client $predis
     * @throws \ErrorException
     */
    private function complete(Client $predis) {
        $this->getClass('exist') ?: $this->loadDatabase();
        $this->saveRedis($predis);
        $predis->disconnect();
    }

    private function setConsole() {
        $this->getClass('expire') ? error_log(
            'redisExpire: ' . $this->getClass('expire'))
            : error_log('redisExpire: 0');
    }

    private function lockClass() {
        self::$lock = self::$class;
        self::resetClass();
    }

    private function isJson($data = null) {
        if (!\is_object(json_decode($data))) {
            new HttpException(
                self::getError('non_json'), -500
            );
        }
    }

    private function generateExpire() {
        return json_encode(['expire' => time() + 
            $this->getLoadCache('interval')]);
    }

    private function generateHash() {
        $this->hashName = sha1($this->loadData);
        $this->hashExpire = sha1($this->loadData.'expire');
    }

    private function getExpire(Client $predis) {
        $this->getClass('expire') ?: $this->setClass('expire',
            json_decode($predis->get($this->hashExpire), true));
        return $this->getClass('expire');
    }

    private function saveRedis(Client $predis) {
        if (!$this->getClass('expire')) {
            $predis->set($this->hashName, $this->getClass('fetch'));
            $predis->set($this->hashExpire, $this->generateExpire());
        }
    }

    private function getResponse($response) {
        switch ($response) {
            case \is_object($response):
                $this->setClass('fetch', json_encode($response));
                $this->isJson($this->getClass('fetch'));
                break;
            case \is_string($response):
                $this->setClass('fetch', $response);
                $this->isJson($this->getClass('fetch'));
                break;
        }
    }

    private function setFetch(Client $predis) {
        $this->setClass('fetch', $predis->get($this->hashName));
        $this->setClass('exist', true);
    }

    private function isExist(Client $predis) {
        $predis->get($this->hashName)
            ? $this->setFetch($predis) : false;
    }

    private function isExpired(Client $predis) { 
        if ($this->getClass('exist')) {
            !$this->getExpire($predis)['expire'] - time() > 0
                ?: $this->setClass('expire',
                $this->getExpire($predis)['expire'] - time());
        }
    }
}