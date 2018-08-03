<?php

namespace Kaleido\Http;

class Worker
{
    const errorPath = '../../../error.json';
    public static $timing = [];
    public static $class = [];
    public static $errorInfo;
    public $handle = [];
    public $route = [];
    public $taskId;

    /**
     * @return mixed
     * @throws \ErrorException
     */
    protected function load() {
        (new Loader())->_load();
        return $this->route = json_decode(
            Loader::fetch(), true
        );
    }

    /**
     * @param bool $taskId
     */
    protected function matchTaskId($taskId = false) {
        $taskId ?: $taskId = $this->taskId;
        if (array_key_exists($taskId, $this->route)) {
            foreach ((array)$this->route[$taskId] as $key => $value) {
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

    protected function setReplace($replace, $subject, $saveName) {
        \count($replace) 
            ?: $this->setClass($saveName, $subject);
        foreach ($replace as $key => $value) {
            switch ($replace) {
                case \is_array($subject):
                    self::$class[$saveName][$key] = $value;
                    if (!$value) {
                        unset(
                            self::$class[$saveName][$key]
                        );
                    }
                    break;
                case \is_string($subject):
                    $this->setClass(
                        $saveName, $subject = preg_replace(
                        "/{$key}/", $value, $subject
                    ));
                    break;
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

    protected function getEnv($className) {
        $data = Utility::bjsonDecode(
            getenv(strtoupper($className)), true);
        null !== $data ?: $data = []; 
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    protected function setTiming($name = 'Timing') {
        null !== self::$timing[$name]
            ?: self::$timing[$name] = Utility::millitime();
        if (\is_int(self::$timing[$name])) {
            $time = self::$timing[$name];
            $timing = Utility::millitime() - $time;
            !$this->handle['enable_header']
            ?: header("X-{$name}: ".$timing.'ms');
        }
        return $this;
    }

    protected function switchHandle($action = 'null') {
        $this->handle[$action] ? 
            $this->handle = $this->handle[$action] : false;
        return $this;
    }

    protected static function getError($errorId = null) {
        var_dump(file_exists(self::errorPath));
        if(file_exists(self::errorPath)) {
            $errorInfo = json_encode(
                file_get_contents(self::errorPath), true);
            \is_array($errorInfo) 
                ? self::$errorInfo = $errorInfo[$errorId]
                    : self::$errorInfo = null;
            return self::$errorInfo;
        }
        return self::$errorInfo;
    }
}