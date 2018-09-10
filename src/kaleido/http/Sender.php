<?php

namespace Kaleido\Http;

use Curl\Curl;
use Curl\CaseInsensitiveArray;

class Sender extends Worker
{
    public $httpType = ['get', 'post', 'put', 'head', 'options', 'search', 'patch', 'delete'];
    public $maxSize;
    public $url;
    public $method;
    public $headers = [];
    public $cookies = [];
    public $params = [];

    /**
     * Sender constructor.
     * @param array $payload
     * @throws \ErrorException
     */
    public function __construct(array $payload) {
        parent::unpackItem($payload);
        $this->check();
        $this->handle();
        parent::lockItem(__CLASS__);
    }

    public function check() {
        $this->checkUrl()->checkMethod()
        ->checkMaxSize()->checkParams()
        ->checkCookies()->checkHeaders();
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
        $curl->error ? $this->setBody($curl->response
            ?? $curl->errorMessage, $curl->responseHeaders)
        : $this->setBody($curl->response, $curl->responseHeaders);
    }

    public static function response($encode) {
        return $encode ? json_encode(self::$lock[__CLASS__])
            : (array)self::$lock[__CLASS__];
    }

    private function setTaskId() {
        if (!\is_string($this->taskId)) {
            parent::setItem('taskId', $this->taskId);
        }
        return $this;
    }

    private function setError($error, $errorCode) {
        if ($error && \is_int($errorCode)) {
            parent::setItem('error', 1);
            parent::setItem('errorCode', $errorCode);
        }
    }

    private function checkUrl() {
        if (!preg_match('/https?\:\/\//', $this->url)) {
            new HttpException(self::getError('0x04'), -400);
        }
        return $this;
    }

    private function checkMethod() {
        if (!\in_array($this->method, $this->httpType, true)) {
            new HttpException(self::getError('0x03'), -400);
        }
        return $this;
    }

    private function checkParams() {
        if (!is_array($this->params)) {
            $this->params = [];
        }
        return $this;
    }

    private function checkCookies() {
        if (!\is_array($this->cookies)) {
            $this->cookies = [];
        }
        return $this;
    }

    private function checkHeaders() {
        if (!\is_array($this->headers)) {
            $this->headers = [];
        }
        return $this;
    }

    private function checkMaxSize() {
        if (!\is_int($this->maxSize)) {
            $this->maxSize = 2097152;
        }
        return $this;
    }

    private function isGzip($header) :bool {
        return $header['Content-Encoding'] !== 'gzip' ? 0 : true;
    }

    private function setBody($body, CaseInsensitiveArray $header) {
        if (\is_object($body)) {
            parent::setItem('respType', 'text');
            parent::setItem('body', json_encode($body));
        } elseif ($this->isGzip($header)) {
            parent::setItem('respType', 'gzip');
            parent::setItem('body', base64_encode($body));
        } else {
            parent::setItem('respType', 'text');
            parent::setItem('body', $body);
        }
    }

    private function setHeaders(CaseInsensitiveArray $headers) {
        foreach ($headers as $key => $value) {
            if ($key !== 'Set-Cookie') {
                self::$item['headers'][$key] = $value;
            }
        }
    }

    private function setCookies($cookies) {
        if (\is_array($cookies)) {
            parent::setItem('cookies', $cookies);
        }
    }
}