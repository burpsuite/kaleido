<?php

namespace Kaleido\Http;

class Worker
{
    public static $timing = [];
    public static $class = [];
    public $handle = [];
    public $route = [];
    public $taskId;

    /**
     * @throws \ErrorException
     */
    protected function load() {
        $this->route = json_decode(new Loader, true);
    }

    /**
     * @param bool $taskId
     */
    protected function matchTaskId($taskId = false) {
        if (array_key_exists($taskId ?: $taskId = $this->taskId, $this->route)) {
            foreach ($this->route[$taskId] as $key => $value) {
                !\array_key_exists($key, get_class_vars(\get_class($this)))
                        ?: $this->$key = $value;
            }
        }
    }

    protected static function getItem($name = 'null') {
        return self::$class[$name] ?? null;
    }

    protected static function setItem($name, $value) {
        switch ($name ?: 'null') {
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

    protected function setReplace($replace, $subject, $saveName) {
        foreach (\is_array($replace) ? $replace : [] as $key => $value) {
            switch ($replace) {
                case \is_array($subject):
                    self::$class[$saveName][$key] = $value;
                    if (!$value) {
                        unset(self::$class[$saveName][$key]);
                    }
                    break;
                case \is_string($subject):
                    self::setItem($saveName, $subject = 
                    preg_replace("/{$key}/", $value, $subject));
                    break;
                case null === $replace:
                    self::setItem($saveName, $subject);
                    break;
            }
        }
    }

    protected function unpackItem($data = null) {
        foreach (\is_array($data) ? $data : (array)$this->$data as $key => $value) {
            $this->$key = $value;
        }
    }

    protected function getEnv($className) {
        null !== ($data = Utility::bjsonDecode(getenv(
            strtoupper($className)), true)) ?: $data = [];
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    protected function setTiming($name = 'Timing') {
        null !== ($time = self::$timing[$name]) ?: self::$timing[$name] = Utility::millitime();
        $time && $this->handle['enable_header'] && \is_int(self::$timing[$name])
            ? header("X-{$name}: ". (Utility::millitime() - self::$timing[$name]).'ms') : null;
        return $this;
    }

    protected function switchHandle($action = 'null') {
        !$this->handle[$action] ?: $this->handle = $this->handle[$action];
        return $this;
    }

    protected static function errorItem(callable $call) {
        return $call([
            'abnormal'=> 'target server status is abnormal.',
            'request_host'=> 'request_host and kaleido do not match.',
            'request_method'=> 'request_method and kaleido do not match',
            'request_activity'=> 'request_action and kaleido do not match',
            'non_array'=> 'payload is a non-array type.',
            'non_string'=> 'payload_url is a non-string type.',
            'payload_host'=> 'payload_host is a invalid protocol.',
            'payload_method'=> 'payload_method is a non-string type.',
            'unsupport_type'=> 'payload_method is an unsupported type.',
            'object_id'=> 'object_id is a non-string type.',
            'request_control'=> 'request_control is not in kaleido=>=>control.',
            'file_path'=> 'kaleido configuration file_path is a invalid path.',
            'env_undefined'=> 'kaleido env_configuration is undefined.',
            'non_json'=> 'kaleido configuration is a non-json type.',
            'save_exception'=> 'kaleido record save exception.'
        ]);
    }

    protected static function getError($id = null) :string {
        return self::errorItem(function ($item) use($id) {
            return $item[$id] ?? null;
        });
    }

    protected static function resetClass() :array {
        return self::$class = [];
    }
}