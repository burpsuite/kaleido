<?php

namespace Kaleido\Http;

use Curl\Curl;
use Predis\Client;

class Loader extends Worker
{
    public $allow = ['regular'];
    public $type;
    public $path;
    public $cache = [];
    private static $lock = [];

    /**
     * @throws \ErrorException
     */
    public function _load() {
        $this->getEnv('dbinfo'); 
        $this->detect();
        $this->handle();
        $this->lockClass();
    }

    private function detect() {
        $this->detectType();
        $this->detectRedis();
    }

    private function detectType() { 
        \in_array($this->getCache(
        'saveType'), $this->allow, true)
        ?: $this->setCache('saveInfo',
        getenv($this->getCache(
            'saveInfo')));
    }

    private function detectRedis() { 
        $redis = new Client(
            $this->getCache('saveInfo'));
        $this->redisExist($redis);
        $this->redisExpire($redis);
        $redis->disconnect();
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
     * @throws \LeanCloud\CloudException
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

    private function getCache($name) {
        return $this->cache[$name] ?? false;
    }

    private function setCache($name, $value) {
        return \is_string($name)
            ? $this->cache[$name] = $value
                : false;
    }

    /**
     * @throws \ErrorException
     */
    private function handle() {
        $this->fetchData();
        $this->redisSave();
        $this->setConsole();
    }

    /**
     * @throws \ErrorException
     */
    private function fetchData() {
        if (!$this->getClass('expired')) {
            switch ($this->type) {
                case 'local':
                    $file = file_get_contents($this->path);
                    $this->setClass('fetch', $file);
                    $this->isJson($this->getClass('fetch'));
                    break;
                case 'remote':
                    $curl = new Curl;
                    $curl->get($this->path);
                    $this->getResponse($curl->response);
                    break;  
            }
        }
    }

    private function setConsole() {
        $this->getClass('expired')
         ? error_log('redisExpire: '.
        $this->getClass('expired'))
         : error_log('redisExpire: 0');
    }

    private function getResponse($response) {
        switch ($response) {
            case \is_object($response):
                $this->setClass('fetch', 
                    json_encode($response));
                $this->isJson(
                    $this->getClass('fetch'));
                break;
            case \is_string($response):
                $this->setClass(
                    'fetch', $response);
                $this->isJson(
                    $this->getClass('fetch'));
                break;
        }
    }

    private function isJson($data) {
        if (!\is_object(json_decode($data))) {
            new HttpException(
                self::error['non_json'], -500
            );
        }
    }

    private function redisExist(Client $redis) { 
        if ($redis->get(sha1($this->path))) {
            $this->setClass('fetch',
                $redis->get(sha1($this->path)));
            $this->setClass('exist', true);
        }
    }

    private function redisExpire(Client $redis) { 
        if ($this->getClass('exist')) {
            $expire = json_decode($redis->get(
                sha1($this->path.'expired')), true);
            if ($expire['expired'] - time() > 0) {
                $this->setClass('expired',
                    $expire['expired'] - time());
            }
        }
    }

    private function redisSave() {
        if (!$this->getClass('expired')) {
            $redis = new Client(
                $this->getCache('saveInfo'));
            $expire = json_encode(
                ['expired' => time() + 
            $this->getCache('interval')]);
            $redis->set(sha1($this->path),
                $this->getClass('fetch'));
            $redis->set(sha1($this->path.
                'expired'), $expire);
            $redis->disconnect();
        }
    }
}