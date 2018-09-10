<?php

namespace Kaleido\Http;

use Curl\Curl;
use Predis\Client;

class Loader extends Worker
{
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
        $this->unpackItem('loadInfo');
        $this->handle();
        parent::lockItem(__CLASS__);
    }

    public function __toString() {
        return (string)self::fetch();
    }

    /**
     * @throws \ErrorException
     */
    private function handle() {
        switch ($this->loadType) {
            case 'local':
                $this->loadDatabase();
                break;
            case 'remote':
                $this->unLoadCache();
                $this->complete();
                break;
        }
    }

    /**
     * @throws \ErrorException
     */
    private function complete() {
        if (\count($this->loadCache) > 1) {
            $redis = $this->predis();
            $this->checkLoadData();
            $this->generateHash();
            $this->unpackExpire($redis);
            $this->isExist($redis);
            $this->isExpired();
            $this->checkValidCache();
            $this->saveRedis($redis);
            $redis->disconnect();
            $this->setConsole();
        } elseif (!\count($this->loadCache)) {
            $this->loadDatabase();
        }
    }

    private function unLoadCache() {
        if ('dynamic' === $this->cacheType()) {
            $this->setLoadCache('data', strtoupper(
                getenv($this->getLoadCache('data'))));
        }
    }

    private function cacheType() {
        return $this->loadCache['type'] ?? false;
    }

    /**
     * @throws \ErrorException
     */
    private function loadDatabase() {
        switch ($this->loadType) {
            case 'local':
                $this->local('fetch', $this->loadData);
                $this->isJson(parent::getItem('fetch'));
                break;
            case 'remote':
                $curl = new Curl;
                $curl->get($this->loadData);
                $this->getResponse($curl->response);
                break;  
        }
    }

    public static function fetch() {
        \is_string(parent::$lock[__CLASS__]['fetch'])
            ?: new HttpException(parent::getError('0x05'), 500);
        return parent::$lock[__CLASS__]['fetch'];
    }

    private function local($setName, $fileName) {
        !\is_file($fileName) ?: parent::setItem($setName,
            file_get_contents($fileName));
    }

    private function checkLoadData() {
        if (null === $this->getLoadCache('data')) {
            new HttpException(
                self::getError('load_failed'), 500
            );
        }
    }

    private function predis() :Client {
        return new Client($this->getLoadCache('data'));
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
        new Sender(Encoder::payload(false));
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
     * @throws \ErrorException
     */
    private function checkValidCache() {
        parent::getItem('exist') || !parent::getItem('expired')
                ?: $this->loadDatabase();
    }

    private function setConsole() {
        parent::getItem('expired') ? error_log('redisExpire: 0')
         : error_log('redisExpire: '. (parent::getItem('expire') - time()));
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
        $this->hashExpire = sha1($this->loadData.'expire');
        $this->hashName = sha1($this->loadData);
    }

    private function unpackExpire(Client $predis) {
        parent::getItem('expire') ?: parent::setItem(
            'expire', json_decode($predis->get(
                $this->hashExpire), true)['expire']);
        return parent::getItem('expire');
    }

    private function saveRedis(Client $predis) {
        if (parent::getItem('expired')) {
            $predis->set($this->hashName, parent::getItem('fetch'));
            $predis->set($this->hashExpire, $this->generateExpire());
        }
    }

    private function getResponse($response) {
        if (\is_object($response)) {
            parent::setItem('fetch', json_encode($response));
            $this->isJson(parent::getItem('fetch'));
        } elseif (\is_string($response)) {
            parent::setItem('fetch', $response);
            $this->isJson(parent::getItem('fetch'));
        }
    }

    private function setFetch(Client $predis) {
        parent::setItem('fetch', $predis->get($this->hashName));
        !parent::getItem('fetch') ?: parent::setItem('exist', true);
    }

    private function isExist(Client $predis) {
        $predis->get($this->hashName)
            ? $this->setFetch($predis) : false;
    }

    private function isExpired() {
        if (parent::getItem('expire') - time() < 0) {
            parent::setItem('expired', true);
        }
    }
}