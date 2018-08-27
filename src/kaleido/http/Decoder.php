<?php

namespace Kaleido\Http;

class Decoder extends Worker
{
    private static $handleItem = [];
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
        parent::unpackItem($response);
        parent::matchTaskId();
        $this->handle();
        $this->lockClass();
    }

    private function handle() {
        parent::switchHandle('response');
        $this->setHandle()->checkError();
        $this->setHeaders()->setCookies();
        $this->setTiming()->setUniqueId()
        ->setCurrentDate()->setBody();
    }

    private function checkError() {
        if (!self::getHandle('allow_error')) {
            $this->error && \is_int($this->errorCode)
                ? new HttpException(self::getError('abnormal'),
            $this->errorCode) : false;
        }
    }

    private function lockClass() {
        self::$lock = self::$class;
        self::resetClass();
    }

    public static function getHandle($item = null) {
        return self::$handleItem[$item] ?? null;
    }

    private function setHandle() {
        !\is_array($this->handle) ?: self::$handleItem = $this->handle;
        return $this;
    }

    public static function class($encode) {
        return $encode ? json_encode(self::$lock)
            : (array)self::$lock;
    }

    public static function getBody() {
        return \is_string(self::$lock['body'])
            ? self::$lock['body'] : 'error_body';
    }

    private function setCurrentDate() {
        !self::getHandle('enable_header')
            ?: header('X-Response-Date:'. gmdate('c'));
        return $this;
    }

    private function setUniqueId() {
        !self::getHandle('enable_header') ?: header(
            'X-Unique-Id:'. str_replace('.', '', uniqid('', true)));
        return $this;
    }

    private function setBody() {
        if ($this->respType === 'gzip') {
            $body = Utility::gzbaseDecode($this->body);
            $this->setReplace(self::getHandle('body'), $body, 'body');
            $this->patchBody();
            parent::setItem('body', gzencode(parent::getItem('body')));
        } elseif('text' === $this->respType) {
            $this->setReplace(self::getHandle('body'), $this->body, 'body');
            $this->patchBody();
        }
        return $this;
    }

    private function patchBody() {
        if (\is_array(json_decode(parent::getItem('body'), true))) {
            \is_array(self::getHandle('body_patch'))
                ? $patch = self::getHandle('body_patch') : $patch = [];
            $body = json_decode(parent::getItem('body'), true);
            parent::setItem('body', json_encode(
                    array_replace_recursive($body, $patch)
                )
            );
        }
    }

    private function setHeaders() {
        if (self::getHandle('enable_header')) {
            parent::setItem('headers', $this->headers);
            $this->setReplace(self::getHandle('header'), $this->headers, 'headers');
            \is_array(parent::getItem('headers')) ? $data = parent::getItem('headers') : $data = [];
            foreach ($data as $key => $value) {
                $key === 'Status-Line' ? header((string) $value)
                    : header("{$key}: {$value}");
            }
        }
        return $this;
    }

    private function setCookies() {
        if (self::getHandle('enable_cookie')) {
            parent::setItem('cookies', $this->cookies);
            $this->setReplace(self::getHandle('cookie'), $this->cookies, 'cookies');
            \is_array(parent::getItem('cookies')) ? $item = parent::getItem('cookies') : $item = [];
            foreach ($item as $key => $value) {
                setcookie($key, $value);
            }
        }
        return $this;
    }
}