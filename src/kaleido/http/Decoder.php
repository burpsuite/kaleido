<?php

namespace Kaleido\Http;

class Decoder
{
    public static $payload = [];
    public $route_info = [];
    public $task_id;
    public $error = false;
    public $error_code = 0;
    public $action = [];
    public $res_type;
    public $body;
    public $response_handle = [];
    public $headers = [];
    public $cookies = [];
    public $timing;

    /**
     * Decoder constructor.
     * @param array $response
     * @throws \ErrorException
     */
    public function __construct(array $response) {
        $this->_load();
        $this->setResponse($response);
        $this->matchTaskId();
        $this->handle();
    }

    /**
     * @throws \ErrorException
     */
    private function _load() {
        (new Loader())->loadfile();
        return $this->route_info = json_decode(Loader::fetch(), true);
    }

    private function matchTaskId() {
        if (array_key_exists($this->task_id, $this->route_info)) {
            foreach ((array)$this->route_info[$this->task_id] as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    private function setResponse($response) {
        if (\is_array($response)) {
            foreach ($response as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    private function handle() {
        $this->setError();
        $this->setHeaders();
        $this->setTiming();
        $this->setUniqId();
        $this->setCookies();
        $this->setBody();
    }

    public static function getBody() {
        return \is_string(self::$payload['body'])
            ? self::$payload['body'] : 'error_body';
    }

    private function getPayload($name = 'null') {
        return self::$payload[$name] ?? null;
    }

    private function setPayload($name, $value) {
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

    private function setError() {
        if ($this->error && \is_int($this->error_code)) {
            new HttpException(
                'target server status is abnormal.',
                $this->error_code
            );
        }
    }

    private function setUniqId() {
        if ($this->action['response_header']) {
            header('Kaleido-UniqId:'.uniqid('kd_', true));
        }
    }

    private function setBody() {
        switch ($this->res_type) {
            case 'gzip':
                $decode_body = Utility::gzbaseDecode($this->body);
                $this->setSort(
                    $this->response_handle['body'], 
                    $decode_body, 
                    'body'
                );
                $this->patchBody();
                $this->setPayload(
                    'body',
                    gzencode($this->getPayload('body'))
                );
                break;
            case 'text':
                $this->setSort(
                    $this->response_handle['body'], 
                    $this->body, 
                    'body'
                );
                $this->patchBody();
                break;
            default:
        }
    }

    private function patchBody() {
        if (\is_array(json_decode($this->getPayload('body'), true))) {
            \is_array($this->response_handle['body_patch'])
                ? $patch = $this->response_handle['body_patch']
                    : $patch = [];
            $body = json_decode(
                $this->getPayload('body'),
                true
            );
            $this->setPayload(
                'body',
                json_encode(array_replace_recursive(
                        $body,
                        $patch
                ))
            );
        }
    }

    private function setHeaders() {
        if (\is_array($this->headers) && $this->action['response_header']) {
            $this->setPayload('headers', $this->headers);
            $this->setSort($this->response_handle['header'], $this->headers, 'headers');
            foreach ((array)$this->getPayload('headers') as $key => $value) {
                header("{$key}: {$value}");
            }
        }
    }

    private function setCookies() {
        if (\is_array($this->cookies) && $this->action['response_cookie']) {
            $this->setPayload('cookies', $this->cookies);
            $this->setSort($this->response_handle['cookie'], $this->cookies, 'cookies');
            foreach ((array)$this->getPayload('cookies') as $key => $value) {
                setcookie($key, $value);
            }
        }
    }

    private function setTiming() {
        if (\is_string($this->timing) && $this->action['response_header']) {
            header("Kaleido-Timing: {$this->timing}");
        }
    }

    private function setSort(array $rep_list, $subject, $save_name = 'null') {
        if (!\count($rep_list)) {
            $this->setPayload($save_name, $subject);
        }
        foreach ($rep_list as $key => $value) {
            switch ($rep_list) {
                case \is_array($subject):
                    self::$payload[$save_name][$key] = $value;
                    if (!$value) {
                        unset(self::$payload[$save_name][$key]);
                    }
                    break;
                case \is_string($subject) && \is_string($value):
                    $filter_res = preg_replace("/{$key}/", $value, $subject);
                    $this->setPayload($save_name, $filter_res);
                    break;
                default:
            }
        }
    }
}