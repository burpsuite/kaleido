<?php

namespace Kaleido\Http;

use Curl\Curl;
use Curl\CaseInsensitiveArray;

class Sender extends Worker
{
    private static $lock;
    public $allow = ['get', 'post', 'put', 'head', 'options', 'search', 'patch', 'delete'];
    public $url;
    public $method;
    public $params = [];
    public $headers = [];
    public $cookies = [];

    /**
     * Sender constructor.
     * @param array $payload
     * @throws \ErrorException
     */
    public function __construct(array $payload) {
        $this->unPayload($payload);
        $this->check();
        $this->handle();
        $this->lockClass();
    }

    public function unPayload($payload) {
        \is_array($payload) ?: new HttpException(
            self::error['non_array'], -500);
        foreach ($payload as $key => $value) {
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
        $this->setTaskId();
        if (!$curl->error) {
            $this->setBody($curl->response,
                $curl->responseHeaders);
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
            : (array)self::$lock;
    }

    private function setTaskId() {
        !\is_string($this->taskId) ?: 
            $this->setClass('taskId', $this->taskId);
            return $this;
    }

    private function setError($error, $errorCode) {
        if ($error && \is_int($errorCode)) {
            $this->setClass('error', 1);
            $this->setClass('errorCode', $errorCode);
        }
    }

    private function checkUrl() {
        \is_string($this->url) ?: new HttpException(
                self::error['non_string'], -500);
        if (!preg_match('/https?\:\/\//', $this->url)) {
            new HttpException(
                self::error['payload_host'], -400
            );
        }
        return $this;
    }

    private function checkMethod() {
        \is_string($this->method) ?: new HttpException(
            self::error['payload_method'], -500);
        if (!\in_array($this->method, $this->allow, true)) {
            new HttpException(
                self::error['unsupport_type'], -400
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

    private function isGzip($encode) :bool {
        \is_array($encode) ?: $encode = [];
        return $encode['Content-Encoding']
            !== 'gzip' ? 0 : true;
    }

    private function setBody(Curl $respBody, Curl $respHeader) {
        switch ($respBody) {
            case \is_object($respBody):
                $this->setClass('respType', 'text');
                $this->setClass('body', 
                    json_encode($respBody));
                break;
            case $this->isGzip($respHeader):
                $this->setClass('respType', 'gzip');
                $this->setClass('body', 
                    base64_encode($respBody));
                break;
            default:
                $this->setClass('respType', 'text');
                $this->setClass('body', $respBody);
                break;
        }
    }

    private function setHeaders(CaseInsensitiveArray $headers) {
        foreach ($headers as $key => $value) {
            if ($key !== 'Set-Cookie') {
                self::$class['headers'][$key] = $value;
            }
        }
    }

    private function setCookies($cookies) {
        switch ($cookies) {
            case \is_array($cookies):
                $this->setClass('cookies', $cookies);
                break;
        }
    }
}