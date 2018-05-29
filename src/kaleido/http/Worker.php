<?php

namespace Kaleido\Http;

class Worker
{
    public $route_info = [];
    public $task_id;
    public static $payload = [];

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

    protected function setPayload($name, $value) {
        \is_string($name) ?: $name = 'null';
        switch ($name) {
            case \is_array($value) && !\count($value):
                self::$payload[$name] = null;
                break;
            case null === $value:
                unset(self::$payload[$name]);
                break;
            default:
                self::$payload[$name] = $value;
                break;
        }
    }

    protected function setSort(array $rep_list, $subject, $save_name = 'null') {
        \count($rep_list) ?: $this->setPayload($save_name, $subject);
        foreach ($rep_list as $key => $value) {
            switch ($rep_list) {
                case \is_array($subject):
                    self::$payload[$save_name][$key] = $value;
                    if (!$value) {
                        unset(self::$payload[$save_name][$key]);
                    }
                    break;
                case \is_string($subject):
                    $filter_res = preg_replace("/{$key}/", $value, $subject);
                    $this->setPayload($save_name, $filter_res);
                    break;
                default:
            }
        }
    }
}