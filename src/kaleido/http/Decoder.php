<?php

namespace Kaleido\Http;

class Decoder extends Worker
{
    private static $handleItem = [];
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
        parent::lockItem(__CLASS__);
    }

    private function handle() {
        parent::switchHandle('response');
        $this->setHandle()->checkError();
        $this->setHeaders()->setCookies();
        $this->setTiming()->setRequestId()
        ->setResponseDate()->setBody();
    }

    private function checkError() {
        if (!self::getHandle('allow_error')) {
            $this->error && \is_int($this->errorCode)
                ? new HttpException(self::getError('abnormal'),
            $this->errorCode) : false;
        }
    }

    public static function getHandle($item = null) {
        return self::$handleItem[$item] ?? null;
    }

    private function setHandle() {
        if (\is_array($this->handle)) {
            self::$handleItem = $this->handle;
        }
        return $this;
    }

    public static function class($encode) {
        return $encode ? json_encode(parent::$lock[__CLASS__])
            : (array)parent::$lock[__CLASS__];
    }

    public static function getBody() {
        \is_string(parent::$lock[__CLASS__]['body'])
            ?: new HttpException(parent::getError('0x02'), 500);
        return parent::$lock[__CLASS__]['body'];
    }

    private function setResponseDate() {
        if (self::getHandle('enable_header')) {
            header('X-Response-Date:'. gmdate('c'));
        }
        return $this;
    }

    private function setRequestId() {
        if (self::getHandle('enable_header')) {
            header('X-Request-Id:'. str_replace('.', '', uniqid('', true)));
        }
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
            foreach (\is_array(parent::getItem('headers')) ?
                         parent::getItem('headers') : [] as $key => $value) {
                $key === 'Status-Line' ? header((string) $value) : header("{$key}: {$value}");
            }
        }
        return $this;
    }

    private function setCookies() {
        if (self::getHandle('enable_cookie')) {
            parent::setItem('cookies', $this->cookies);
            $this->setReplace(self::getHandle('cookie'), $this->cookies, 'cookies');
            foreach (\is_array(parent::getItem('cookies')) ?
                         parent::getItem('cookies') : [] as $key => $value) {
                setcookie($key, $value);
            }
        }
        return $this;
    }
}