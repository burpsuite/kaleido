<?php

namespace Kaleido\Http;

use Curl\Curl;
use Predis\Client;

class Loader extends Worker
{

    public static $lock = [];
    public $allow = ['dynamic'];
    public $loadType;
    public $loadInfo;
    public $loadData;
    public $loadCache = [];

    public function _load() {
        $this->getEnv('database');
        $this->loadType();
    }

    public function loadType() {
        switch ($this->loadType) {
            case 'local':
                $this->unLoadInfo();
                $this->fetchLoadData();
                break;
            case 'remote':
                $this->unLoadInfo();
                $this->isDynamicType();
                $this->loadRedis();
                $this->complete();
                break;
        }
    }

    private function lockClass() {
        self::$lock = self::$class;
        self::$class = [];
    }

    public static function fetch() {
        return \is_string(self::$lock['fetch'])
            ? self::$lock['fetch'] : 'error_fetch';
    }

    /**
     * @param $taskId
     * @param $url
     * @return mixed|string
     * @throws \ErrorException
     */
    public function listenHttp($taskId, $url) {
        new Encoder($taskId, $url);
        new Sender(Encoder::class(false));
        new Decoder(Sender::response(false));
        new Recorder(Encoder::class(false),
            Sender::response(false));
        return Decoder::getBody();
    }

    /**
     * @param $action
     * @param $objectId
     * @return mixed
     * @throws \ErrorException
     */
    public function replayHttp($action, $objectId) {
        new Replay($action, $objectId);
        return Replay::getBody();
    }

    private function complete() {
        if (!$this->getClass('isExist')) {
            $this->fetchLoadData();
            $this->saveRedis();
        }
    }

    private function saveRedis() {
        if (!$this->getClass('expired')) {
            $predis = new Client($this->getLoadCache('data'));
            $expire = json_encode(['expired' => time() + 
                $this->getLoadCache('interval')]);
            $redis->set(sha1($this->loadData), $this->getClass('fetch'));
            $redis->set(sha1($this->loadData. 'expired'), $expire);
            $redis->disconnect();
        }
    }

    /**
     * @throws \ErrorException
     */
    private function fetchLoadData() {
        switch ($this->loadType) {
            case 'local':
                $this->setClass('fetch', 
                    file_get_contents($this->loadData));
                $this->isJson($this->getClass('fetch'));
                break;
            case 'remote':
                $curl = new Curl;
                $curl->get($this->loadData);
                $this->getResponse($curl->response);
                break;  
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

    private function loadRedis() {
        if (\count($this->loadCache)) {
            $predis = new Client(
                $this->getLoadCache('data'));
            $this->isExist($predis);
            $this->isExpired($predis);
            $predis->disconnect();
        }
    }

    private function isDynamicType() {
        if (in_array($this->getCacheType, $this->allow)) {
            !\count($this->loadCache) ?: 
            $this->setLoadCache('data', getenv(
                 $this->getLoadCache('data')
            ));
        }
    }

    private function isExist(Client $predis) { 
        if ($predis->get(sha1($this->loadData))) {
            $this->setClass('fetch', 
                $predis->get(sha1($this->loadData)));
            $this->setClass('isExist', true);
        }
    }

    private function isExpired(Client $predis) { 
        if ($this->getClass('isExist')) {
            $expire = json_decode($predis->get(
                sha1($this->loadData.'expired')), true);
            if ($expire['expired'] - time() > 0) {
                $this->setClass('expired',
                    $expire['expired'] - time());
            }
        }
    }

    private function unLoadInfo() {
        foreach ($this->loadInfo as $key => $val) {
            $this->$key = $val;
        }
    }

    private function getLoadCache($name) {
        return $this->loadCache[$name] ?? false;
    }

    private function setLoadCache($name, $value = null) {
        !\is_string($name) && !$this->loadCache[$name]
            ?: $this->loadCache[$name] = $value;
    }

    private function getCacheType() {
        return $this->loadCache['type'] ?? false;
    }
}