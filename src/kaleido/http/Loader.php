<?php

namespace Kaleido\Http;

use Curl\Curl;
use Predis\Client;

class Loader extends Worker
{
    public $file_type;
    public $file_path;
    public $enable_cache = false;
    public $cache_expire = 3600;
    public $redis_url;
    private static $lock = [];
    public static $redis = [
        'exist'  => false,
        'expire' => 0,
        'fetch'  => null
    ];

    /**
     * @throws \ErrorException
     */
    public function loadfile() {
        $this->getEnv('dbinfo');
        $this->check();
        $this->handle();
        $this->lockClass();
    }

    private function lockClass() {
        self::$lock = self::$class;
        self::$class = [];
    }

    /**
     * @throws \ErrorException
     */
    public function flushDB() {
        $this->getEnv('dbinfo');
        $this->handle();
    }

    public static function fetch() {
        return \is_string(self::$lock['fetch'])
            ? self::$lock['fetch'] : 'error_fetch';
    }

    private function check() {
        $this->checkType();
        $this->checkRedis();
    }

    /**
     * @throws \ErrorException
     */
    private function handle() {
        $this->fetchData();
        $this->updateRedis();
        $this->setConsole();
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
        new Recorder(
            Encoder::class(false), Sender::response(false)
        );
        return Decoder::getBody();
    }

    /**
     * @param $action
     * @param $object_id
     * @return mixed
     * @throws \ErrorException
     */
    public function replayHttp($action, $object_id) {
        new Replay($action, $object_id);
        return Replay::getBody();
    }

    private function checkType() {
        switch ($this->file_type) {
            case 'local':
                if (!is_file($this->file_path)) {
                    new HttpException(
                        self::error['file_path'], -500
                    );
                }
                break;
            case 'remote':
                if (!preg_match('/https?\:\/\//', $this->file_path)) {
                    new HttpException(
                        self::error['file_path'], -500
                    );
                }
                break;
            default:
                new HttpException(
                    self::error['env_undefined'], -500
                );
                break;
        }
        return $this;
    }

    private function checkRedis() {
        if ($this->enable_cache && $this->redis_url) {
            $redis = new Client($this->redis_url);
            $this->checkExist($redis);
            $this->checkExpire($redis);
            $redis->disconnect();
        }
    }

    /**
     * @throws \ErrorException
     */
    private function fetchData() {
        if (!$this->getClass('expire')) {
            switch ($this->file_type) {
                case 'local':
                    $file = file_get_contents($this->file_path);
                    $this->setClass('fetch', $file);
                    $this->isJson($this->getClass('fetch'));
                    break;
                case 'remote':
                    $curl = new Curl;
                    $curl->get($this->file_path);
                    $this->getResponse($curl->response);
                    break;
            }
        }
    }

    private function isJson($data) {
        if (!\is_object(json_decode($data))) {
            new HttpException(
                self::error['non_json'], -500
            );
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

    private function checkExist(Client $redis_obj) {
        if ($redis_obj->get(sha1($this->file_path))) {
            $this->setClass('fetch', $redis_obj->get(sha1($this->file_path)));
            \is_string($redis_obj->get(sha1($this->file_path)))
                ? $this->setClass('exist', true) : false;
        }
    }

    private function checkExpire(Client $redis_obj) {
        if ($this->getClass('exist')) {
            $expire = json_decode($redis_obj->get(sha1($this->file_path.'expire')), true);
            if ($expire['expire_time'] - time() > 0) {
                $this->setClass('expire', $expire['expire_time'] - time());
            }
        }
    }

    private function updateRedis() {
        if ($this->enable_cache && !$this->getClass('expire')) {
            $redis = new Client($this->redis_url);
            $expire_time = json_encode(['expire_time' => time() + $this->cache_expire]);
            $redis->set(sha1($this->file_path), $this->getClass('fetch'));
            $redis->set(sha1($this->file_path.'expire'), $expire_time);
            $redis->disconnect();
        }
    }

    private function setConsole() {
        $this->getClass('expire')
         ? error_log('redis_expire: '.$this->getClass('expire'))
                : error_log('redis_expire: 0');
    }
}