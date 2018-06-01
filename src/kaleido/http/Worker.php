<?php

namespace Kaleido\Http;

class Worker
{
    public static $timing = [];
    public static $class = [];
    public $route_info = [];
    public $task_id;
    const env_name = [
        'record' => 'KALEIDO_RECORD',
        'dbinfo' => 'KALEIDO_DBINFO'
    ];
    const error_message = [
        'abnormal' => 'target server status is abnormal.',
        'request_host' => 'request_host and kaleido do not match.',
        'request_method' => 'request_method and kaleido do not match',
        'non_array' => 'payload is a non-array type.',
        'non_string' => 'payload_url is a non-string type.',
        'payload_host' => 'payload_host is a invalid protocol.',
        'payload_method' => 'payload_method is a non-string type.',
        'unsupported_type' => 'payload_method is an unsupported type.',
        'object_id' => 'object_id is a non-string type.',
        'request_action' => 'request_action is not in kaleido::action.',
        'file_path' => 'kaleido configuration file_path is a invalid path.',
        'env_undefined' => 'kaleido env_configuration is undefined.',
        'non_json' => 'kaleido configuration is a non-json type.'
    ];

    /**
     * @return mixed
     * @throws \ErrorException
     */
    protected function _load() {
        (new Loader())->loadfile();
        return $this->route_info = json_decode(
            Loader::fetch(), true
        );
    }

    /**
     * @param bool $task_id
     */
    protected function matchTaskId($task_id = false) {
        $task_id ?: $task_id = $this->task_id;
        if (array_key_exists($task_id, $this->route_info)) {
            foreach ((array)$this->route_info[$task_id] as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    protected function setClass($name, $value) {
        \is_string($name) ?: $name = 'null';
        switch ($name) {
            case \is_array($value) && !\count($value):
                self::$class[$name] = null;
                break;
            case null === $value:
                unset(self::$class[$name]);
                break;
            default:
                self::$class[$name] = $value;
                break;
        }
    }

    protected function setSort(array $rep_list, $subject, $save_name = 'null') {
        \count($rep_list) ?: $this->setClass($save_name, $subject);
        foreach ($rep_list as $key => $value) {
            switch ($rep_list) {
                case \is_array($subject):
                    self::$class[$save_name][$key] = $value;
                    if (!$value) {
                        unset(self::$class[$save_name][$key]);
                    }
                    break;
                case \is_string($subject):
                    $filter_res = preg_replace("/{$key}/", $value, $subject);
                    $this->setClass($save_name, $filter_res);
                    break;
                default:
            }
        }
    }

    protected static function getBody() {
        return \is_string(self::$class['body'])
            ? self::$class['body'] : 'error_body';
    }

    protected function getClass($name = 'null') {
        return self::$class[$name] ?? null;
    }

    protected function getEnv($env_name) {
        if ($env = getenv(self::env_name[$env_name])) {
            $env_info = Utility::bjsonDecode($env, true);
            foreach ((array)$env_info as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    protected function setTiming($name = 'Timing') {
        switch ($name) {
            case isset(self::$timing[$name]):
                self::$timing[$name] = 
                    Utility::millitime() - self::$timing[$name];
                $this->action['response_header'] ? 
                header(
                    "X-{$name}: ".self::$timing[$name].'ms'
                ) : false;
                break;
            default:
                self::$timing[$name] = 
                    Utility::millitime();
                break;
        }
        return $this;
    }
}