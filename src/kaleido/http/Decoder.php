<?php

namespace Kaleido\Http;

class Decoder extends Worker
{
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

    private function setResponse($response) {
        if (\is_array($response)) {
            foreach ($response as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    private function handle() {
        $this->checkError();
        $this->setHeaders()->setTiming()
        ->setUniqueId()->setCookies()->setBody();
    }

    public static function getBody() {
        return \is_string(self::$payload['body'])
            ? self::$payload['body'] : 'error_body';
    }

    private function getPayload($name = 'null') {
        return self::$payload[$name] ?? null;
    }

    private function checkError() {
        if ($this->error && \is_int($this->error_code)) {
            new HttpException(
                'target server status is abnormal.',
                $this->error_code
            );
        }
    }

    private function setUniqueId() {
        if ($this->action['response_header']) {
            header('X-UniqueId:'.uniqid('', true));
        }
        return $this;
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
                    gzencode(
                        $this->getPayload('body')
                    )
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
        return $this;
    }

    private function patchBody() {
        if (\is_array(json_decode($this->getPayload('body'), true))) {
            \is_array($this->response_handle['body_patch'])
                ? $patch = $this->response_handle['body_patch']
                    : $patch = [];
            $body = json_decode($this->getPayload('body'), true);
            $this->setPayload(
                'body',
                json_encode(
                    array_replace_recursive($body, $patch)
                )
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
        return $this;
    }

    private function setCookies() {
        if (\is_array($this->cookies) && $this->action['response_cookie']) {
            $this->setPayload('cookies', $this->cookies);
            $this->setSort($this->response_handle['cookie'], $this->cookies, 'cookies');
            foreach ((array)$this->getPayload('cookies') as $key => $value) {
                setcookie($key, $value);
            }
        }
        return $this;
    }

    private function setTiming() {
        if (\is_string($this->timing) && $this->action['response_header']) {
            header("X-Timing: {$this->timing}");
        }
        return $this;
    }
}