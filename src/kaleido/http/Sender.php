<?php

namespace Kaleido\Http;

use Curl\Curl;

class Sender
{
    public $allow_list = ['get', 'post', 'put', 'head', 'options', 'search', 'patch', 'delete'];
    public $headers = [];
    public $cookies = [];
    public $method;
    public $url;
    public $params = [];
    public $action = [];
    public $task_id;
    public static $response = [];

    /**
     * Sender constructor.
     * @param array $payload
     * @throws \ErrorException
     */
    public function __construct(array $payload) {
        $this->setTiming();
        $this->decode($payload);
        $this->check();
        $this->handle();
        $this->setTiming();
    }

    public function setTiming() {
        null === $this->getResponse('timing')
            ? $this->setResponse('timing', Utility::millitime())
                : $this->setResponse('timing',
                    Utility::millitime() - 
                    $this->getResponse('timing').'ms'
            );
    }

    public function decode($payload) {
        Utility::isArray(
            $payload,
            'the payload is a non-array type.',
            -500
        );
        foreach ((array)$payload as $key => $value) {
            $this->$key = $value;
        }
    }

    public function check() {
        $this->checkUrl();
        $this->checkMethod();
        $this->checkParams();
        $this->checkCookies();
        $this->checkHeaders();
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
        $this->setAction();
        $this->setTaskId();
        if (!$curl->error) {
            $this->setBody($curl);
            $this->setHeaders($curl->responseHeaders);
            $this->setCookies($curl->responseCookies);
        }
    }

    public static function response($encode) {
        return $encode ? json_encode(self::$response)
            : self::$response;
    }

    public function setAction() {
        if (\is_array($this->action) && !$this->getResponse('error')) {
            $this->setResponse('action', $this->action);
        }
    }

    private function setTaskId() {
        if (\is_string($this->task_id)) {
            $this->setResponse('task_id', $this->task_id);
        }
    }

    private function setError($error, $error_code) {
        if ($error && \is_int($error_code)) {
            $this->setResponse('error', 1);
            $this->setResponse('error_code', $error_code);
        }
    }

    private function checkUrl() {
        Utility::isString(
            $this->url,
            'the payload_url is a non-string type.',
            -500
        );
        if (!preg_match('/(http|https)\:\/\//', $this->url)) {
            new HttpException(
                'the payload_host is a invalid protocol.',
                -400
            );
        }
    }

    private function checkMethod() {
        Utility::isString(
            $this->method,
            'the payload_method is a non-string type.',
            -500
        );
        if (!\in_array($this->method, $this->allow_list, true)) {
            new HttpException(
                'the payload_method is an unsupported type.',
                -400
            );
        }
    }

    private function checkHeaders() {
        if (null === $this->headers) {
            $this->headers = [];
        }
    }

    private function checkCookies() {
        if (null === $this->cookies) {
            $this->cookies = [];
        }
    }

    private function checkParams() {
        if (null === $this->params) {
            $this->params = [];
        }
    }

    private function setResponse($name, $value) {
        \is_string($name) ?: $name = 'null';
        switch ($name) {
            case \is_array($value) && !\count($value):
                self::$response[$name] = null;
                break;
            case null === $value:
                unset(self::$response[$name]);
                break;
            default:
                self::$response[$name] = $value;
                break;
        }
    }

    private function getResponse($name = 'null') {
        return self::$response[$name] ?? null;
    }

    private function setBody(Curl $response) {
        switch ($response) {
            case \is_object($response->response):
                $this->setResponse('res_type', 'text');
                $this->setResponse(
                    'body', 
                    json_encode($response->response)
                );
                break;
            case $response->responseHeaders['Content-Encoding'] === 'gzip':
                $this->setResponse('res_type', 'gzip');
                $this->setResponse(
                    'body',
                    base64_encode($response->response)
                );
                break;
            default:
                $this->setResponse('res_type', 'text');
                $this->setResponse(
                    'body', 
                    $response->response
                );
                break;
        }
    }

    private function setHeaders($response_headers) {
        foreach ((object)$response_headers as $key => $value) {
            if ($key !== 'Set-Cookie') {
                self::$response['headers'][$key] = $value;
            }
        }
    }

    private function setCookies($response_cookies) {
        switch ($response_cookies) {
            case \is_array($response_cookies):
                $this->setResponse('cookies', $response_cookies);
                break;
        }
    }
}