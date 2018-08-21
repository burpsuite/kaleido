<?php

namespace Kaleido\Http;

use Curl\Curl;
use Curl\CaseInsensitiveArray;

class Sender extends Worker
{
    private static $lock;
    public $protocol = ['get', 'post', 'put', 'head', 'options', 'search', 'patch', 'delete'];
    public $maxSize;
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
        if (\is_array($payload)) {
            foreach ($payload as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    public function check() {
        $this->checkUrl()->checkMethod()
        ->checkParams()->checkCookies()
        ->checkHeaders()->checkMaxSize();
    }

    /**
     * @throws \ErrorException
     */
    public function handle() {
        $curl = new Curl();
        $this->setTaskId();
        $curl->setMaxFilesize($this->maxSize);
        $curl->setHeaders($this->headers);
        $curl->setCookies($this->cookies);
        $curl->{$this->method}($this->url, $this->params);
        $this->setError($curl->error, $curl->errorCode);
        $this->setHeaders($curl->responseHeaders);
        $this->setCookies($curl->responseCookies);
        $curl->error ? $this->setBody(
            $curl->response ?: $curl->errorMessage,
            $curl->responseHeaders) : $this->setBody(
        $curl->response, $curl->responseHeaders);
    }

    private function lockClass() {
        self::$lock = self::$class;
        self::resetClass();
    }

    public static function response($encode) {
        return $encode ? json_encode(self::$lock)
            : (array)self::$lock;
    }

    private function setTaskId() {
        !\is_string($this->taskId) ?:
            parent::setClass('taskId', $this->taskId);
            return $this;
    }

    private function setError($error, $errorCode) {
        if ($error && \is_int($errorCode)) {
            parent::setClass('error', 1);
            parent::setClass('errorCode', $errorCode);
        }
    }

    private function checkUrl() {
        \is_string($this->url) ?: new HttpException(
                self::getError('non_string'), -500);
        if (!preg_match('/https?\:\/\//', $this->url)) {
            new HttpException(
                self::getError('payload_host'), -400
            );
        }
        return $this;
    }

    private function checkMethod() {
        \is_string($this->method) ?: new HttpException(
            self::getError('payload_method'), -500);
        if (!\in_array($this->method, $this->protocol, true)) {
            new HttpException(
                self::getError('unsupport_type'), -400
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
        \is_array($this->cookies)
            ?: $this->cookies = [];
            return $this;
    }

    private function checkHeaders() {
        \is_array($this->headers) 
            ?: $this->headers = [];
            return $this;
    }

    private function checkMaxSize() {
        \is_int($this->maxSize)
            ?: $this->maxSize = 2097152;
    }

    private function isGzip($header) :bool {
        return $header['Content-Encoding']
            !== 'gzip' ? 0 : true;
    }

    private function setBody($body, CaseInsensitiveArray $header) {
        switch ($body) {
            case \is_object($body):
                parent::setClass('respType', 'text');
                parent::setClass('body', json_encode($body));
                break;
            case $this->isGzip($header):
                parent::setClass('respType', 'gzip');
                parent::setClass('body', base64_encode($body));
                break;
            default:
                parent::setClass('respType', 'text');
                parent::setClass('body', $body);
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
        !\is_array($cookies) ?:
            parent::setClass('cookies', $cookies);
    }
}