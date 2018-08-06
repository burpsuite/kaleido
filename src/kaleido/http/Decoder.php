<?php

namespace Kaleido\Http;

class Decoder extends Worker
{
    private static $lock;
    public $error = false;
    public $errorCode = 0;
    public $respType;
    public $body;
    public $headers = [];
    public $cookies = [];

    /**
     * Decoder constructor.
     * @param array $response
     * @throws \ErrorException
     */
    public function __construct(array $response) {
        parent::load();
        $this->setResponse($response);
        parent::matchTaskId();
        $this->handle();
        $this->lockClass();
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
        parent::switchHandle('response');
        $this->setHeaders()->setCookies();
        $this->setTiming()->setUniqueId()
        ->setBody();
    }

    private function checkError() {
        if ($this->error && \is_int($this->errorCode)) {
            new HttpException(
                self::getError('abnormal'), $this->errorCode
            );
        }
    }

    private function lockClass() {
        self::$lock = self::$class;
        self::$class = [];
    }

    public static function class($encode) {
        return $encode ? json_encode(self::$lock)
            : (array)self::$lock;
    }

    protected static function getBody() {
        return \is_string(self::$lock['body'])
            ? self::$lock['body'] : 'error_body';
    }

    private function setUniqueId() {
        if ($this->handle['enable_header']) {
            header('X-UniqueId:'.uniqid('', true));
        }
        return $this;
    }

    private function setBody() {
        switch ($this->respType) {
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
                    $this->body, 'body');
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
        if ($this->handle['enable_header']) {
            $this->setClass('headers', $this->headers);
            $this->setReplace($this->handle['header'], 
                $this->headers, 'headers');
            \count($this->getClass('headers'))
                ? $data = $this->getClass('headers')
                    : $data = [];
            foreach ($data as $key => $value) {
                header("{$key}: {$value}");
            }
        }
        return $this;
    }

    private function setCookies() {
        if ($this->handle['enable_cookie']) {
            $this->setClass('cookies', $this->cookies);
            $this->setReplace($this->handle['cookie'], 
                $this->cookies, 'cookies');
            \count($this->getClass('cookies'))
                ? $data = $this->getClass('cookies')
                    : $data = [];
            foreach ($data as $key => $value) {
                setcookie($key, $value);
            }
        }
        return $this;
    }
}