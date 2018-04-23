<?php

namespace Kaleido\Http;

use Curl\Curl;
use Predis\Client;

class Loader {

	public $env_name = 'kaleido_dbinfo';
	public $file_type;
	public $file_path;
	public $enable_cache = false;
	public $cache_expire = 3600;
	public $redis_url;
	public static $redis = [
		'exist' => false,
		'expire' => 0,
		'fetch' => null
	];

    /**
     * @throws \ErrorException
     */
	public function loadfile() {
		$this->setEnv();
		$this->check();
		$this->handle();
	}

    /**
     * @throws \ErrorException
     */
	public function flushDB() {
	    $this->setEnv();
	    $this->handle();
    }

	public static function fetch() {
        return \is_string(self::$redis['fetch'])
            ? self::$redis['fetch'] : 'error_fetch';
	}

    private function setEnv() {
        if (getenv(strtoupper($this->env_name))) {
        	$env = getenv(strtoupper($this->env_name));
        	$env_info = Utility::bjsonDecode($env, true);
            foreach ((array)$env_info as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    private function getClass($name = 'null') {
        return self::$redis[$name] ?? null;
    }

    private function setClass($name, $value) {
    	\is_string($name) ?: $name = 'error';
        switch ($name) {
            case \is_array($value) && !\count($value):
                self::$redis[$name] = null;
                break;
            case null === $value:
                unset(self::$redis[$name]);
                break;
            default:
                self::$redis[$name] = $value;
                break;
        }
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
     * @param $task_id
     * @param $full_url
     * @param $raw_param
     * @return mixed|string
     * @throws \ErrorException
     * @throws \LeanCloud\CloudException
     */
    public function listenHttp($task_id, $full_url, $raw_param) {
        new Encoder($task_id, $full_url, $raw_param);
        new Sender(Encoder::payload(false));
        new Decoder(Sender::response(false));
        new Recorder(Encoder::payload(false), Sender::response(false));
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
						'kaleido configuration \'file_path\' is a invalid path.',
						-500
					);
				}
				break;
			case 'remote':
				if (!preg_match('/(http|https)\:\/\//', $this->file_path)) {
					new HttpException(
						'kaleido configuration \'file_path\' is a invalid path.',
						-500
					);
				}
				break;
			default:
				new HttpException(
					'kaleido env_configuration is undefined.', 
					-500
				);
				break;
		}
	}

	private function checkRedis() {
		if ($this->enable_cache && null !== $this->redis_url) {
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
				'kaleido configuration is a non-json type.',
				-500
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
		$this->getClass('expire') ?
			error_log('kaleido_redis_expire: '.$this->getClass('expire'))
				: error_log('kaleido_redis_expire: 0');
	}
}