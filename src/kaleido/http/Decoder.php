<?php

namespace Kaleido\Http;

class Decoder extends Worker
{
    public $error = false;
    public $errorCode = 0;
    public $responseType;
    public $body;
    public $headers = [];
    public $cookies = [];

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
        $this->switchHandle('response');
        $this->setHeaders()->setTiming()
        ->setUniqueId()->setCookies()->setBody();
    }

    public static function getBody() {
        return parent::getBody();
    }

    private function checkError() {
        if ($this->error && \is_int($this->errorCode)) {
            new HttpException(
                self::error['abnormal'], $this->errorCode
            );
        }
    }

    private function setUniqueId() {
        if ($this->control['response_header']) {
            header('X-UniqueId:'.uniqid('', true));
        }
        return $this;
    }

    private function setBody() {
        switch ($this->responseType) {
            case 'gzip':
                $body = Utility::gzbaseDecode($this->body);
                $this->setReplace(
                    $this->handle['body'],
                    $body, 'body'
                );
                $this->patchBody();
                $this->setClass(
                    'body', gzencode(
                        $this->getClass('body')
                    )
                );
                break;
            case 'text':
                $this->setReplace(
                    $this->handle['body'], 
                    $this->body, 'body'
                );
                $this->patchBody();
                break;
            default:
        }
        return $this;
    }

    private function patchBody() {
        if (\is_array(json_decode($this->getClass('body'), true))) {
            \is_array($this->handle['body_patch'])
                ? $patch = $this->handle['body_patch']
                    : $patch = [];
            $body = json_decode($this->getClass('body'), true);
            $this->setClass(
                'body', json_encode(
                    array_replace_recursive($body, $patch)
                )
            );
        }
    }

    private function setHeaders() {
        if (\is_array($this->headers) && $this->control['response_header']) {
            $this->setClass('headers', $this->headers);
            $this->setReplace($this->handle['header'], $this->headers, 'headers');
            foreach ((array)$this->getClass('headers') as $key => $value) {
                header("{$key}: {$value}");
            }
        }
        return $this;
    }

    private function setCookies() {
        if (\is_array($this->cookies) && $this->control['response_cookie']) {
            $this->setClass('cookies', $this->cookies);
            $this->setReplace($this->handle['cookie'], $this->cookies, 'cookies');
            foreach ((array)$this->getClass('cookies') as $key => $value) {
                setcookie($key, $value);
            }
        }
        return $this;
    }
}