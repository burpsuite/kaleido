<?php

namespace Kaleido\Http;

use Curl\Curl;
use Curl\CaseInsensitiveArray;

class Sender extends Worker
{
    public $allow_list = ['get', 'post', 'put', 'head', 'options', 'search', 'patch', 'delete'];
    public $url;
    private static $lock;
    public $method;
    public $task_id;
    public $params = [];
    public $action = [];
    public $headers = [];
    public $cookies = [];

    /**
     * Sender constructor.
     * @param array $payload
     * @throws \ErrorException
     */
    public function __construct(array $payload) {
        $this->decode($payload);
        $this->check();
        $this->handle();
        $this->lockClass();
    }

    public function decode($payload) {
        \is_array($payload) 
        ?: new HttpException(
            self::error_message['non_array'], -500
        );
        foreach ((array)$payload as $key => $value) {
            $this->$key = $value;
        }
    }

    public function check() {
        $this->checkUrl()->checkMethod()
        ->checkParams()->checkCookies()
        ->checkHeaders();
    }

    /**
     * @throws \ErrorException
     */
    public function handle() {
        $curl = new Curl();
        $curl->setHeaders($this->headers);
        $curl->setCookies($this->cookies);
        $curl->{$this->method}($this->url, $this->params);
        $this->setError($curl->error, $curl->errorCode);
        $this->setAction()->setTaskId();
        if (!$curl->error) {
            $this->setBody($curl);
            $this->setHeaders($curl->responseHeaders);
            $this->setCookies($curl->responseCookies);
        }
    }

    private function lockClass() {
        self::$lock = self::$class;
        self::$class = [];
    }

    public static function response($encode) {
        return $encode ? json_encode(self::$lock)
            : self::$lock;
    }

    public function setAction() {
        \is_array($this->action) && !$this->getClass('error')
            ? $this->setClass('action', $this->action) : false;
            return $this;
    }

    private function setTaskId() {
        !\is_string($this->task_id) ?: 
            $this->setClass('task_id', $this->task_id);
            return $this;
    }

    private function setError($error, $error_code) {
        if ($error && \is_int($error_code)) {
            $this->setClass('error', 1);
            $this->setClass('error_code', $error_code);
        }
    }

    private function checkUrl() {
        \is_string($this->url) 
        ?: new HttpException(
            self::error_message['non_string'], -500
        );
        if (!preg_match('/https?\:\/\//', $this->url)) {
            new HttpException(
                self::error_message['payload_host'], -400
            );
        }
        return $this;
    }

    private function checkMethod() {
        \is_string($this->method) 
        ?: new HttpException(
            self::error_message['payload_method'], -500
        );
        if (!\in_array($this->method, $this->allow_list, true)) {
            new HttpException(
                self::error_message['unsupported_type'], -400
            );
        }
        return $this;
    }

    private function checkParams() {
        $this->params
            ?: $this->params = [];
            return $this;
    }

    private function checkCookies() {
        $this->cookies
            ?: $this->cookies = [];
            return $this;
    }

    private function checkHeaders() {
        $this->headers 
            ?: $this->headers = [];
            return $this;
    }

    private function setBody(Curl $response) {
        switch ($response) {
            case \is_object($response->response):
                $body = json_encode($response->response);
                $this->setClass('res_type', 'text');
                $this->setClass('body', $body);
                break;
            case $response->responseHeaders['Content-Encoding'] === 'gzip':
                $body = base64_encode($response->response);
                $this->setClass('res_type', 'gzip');
                $this->setClass('body', $body);
                break;
            default:
                $this->setClass('res_type', 'text');
                $this->setClass('body', $response->response);
                break;
        }
    }

    private function setHeaders(CaseInsensitiveArray $response_headers) {
        foreach ($response_headers as $key => $value) {
            if ($key !== 'Set-Cookie') {
                self::$class['headers'][$key] = $value;
            }
        }
    }

    private function setCookies($response_cookies) {
        switch ($response_cookies) {
            case \is_array($response_cookies):
                $this->setClass('cookies', $response_cookies);
                break;
        }
    }
}