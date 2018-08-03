<?php

namespace Kaleido\Http;

class Encoder extends Worker
{
    public $allow = ['post', 'put', 'head', 'options', 'search', 'patch', 'delete'];
    public $method;
    public $host;
    private static $lock = [];

    /**
     * Encoder constructor.
     * @param $taskId
     * @param $url
     * @throws \ErrorException
     */
    public function __construct($taskId, $url) {
        parent::setTiming();
        parent::load();
        parent::matchTaskId($taskId);
        $this->check($url);
        $this->handle($taskId, $url);
        $this->lockClass();
    }

    private function check($url) {
        $this->checkHost($url)
        ->checkMethod();
    }

    private function handle($taskId, $url) {
        $this->switchHandle('request');
        $this->setTaskId($taskId)->setMethod()
        ->setUrl($url)->setUrlParam()->setUrl($url)
        ->setCookie()->setHeader();
    }

    private function lockClass() {
        self::$lock = self::$class;
        self::$class = [];
    }

    public static function class($encode) {
        return $encode ? json_encode(self::$lock)
            : self::$lock;
    }

    private function checkHost($url) {
        if ($this->handle['check_hostname']) {
            $host = preg_replace('/^(https?\:\/\/.*?\..*?)\/.*/', '$1', $url);
            \is_string($this->host) ? $this->host = [$this->host] : false;
            if (!\in_array($host, $this->host, true)) {
                new HttpException(
                    self::getError('request_host'), -400
                );
            }
        }
        return $this;
    }

    private function checkMethod() {
        if ($this->handle['check_method']) {
            $method = strtolower($_SERVER['REQUEST_METHOD']);
            \is_string($this->method) ? $this->method = [$this->method] : false;
            if (!\in_array($method, $this->method, true)) {
                new HttpException(
                    self::getError('request_method'), -400
                );
            }
        }
        return $this;
    }

    private function setTaskId($taskId) {
        !\is_string($taskId) 
            ?: $this->setClass('taskId', $taskId);
        return $this;
    }

    private function setMethod() {
        $this->setClass(
            'method', $this->method = strtolower(
                $_SERVER['REQUEST_METHOD']
            )
        );
        return $this;
    }

    private function setUrlParam() {
        switch ($this->method) {
            case 'get' && $this->handle['fix_urlencode']:
                $this->setClass(
                    'url', $this->getClass(
                        'url').$_SERVER['QUERY_STRING']
                );
                break;
            case 'get':
                $this->setClass('params', $_GET);
                $this->setReplace(
                    $this->handle['url_param'], $_GET, 'params'
                );
                break;
            case \in_array($this->method, $this->allow, true) && \count($_POST):
                $this->combineUrlParam();
                $this->setClass('params', $_POST);
                $this->setReplace(
                    $this->handle['form_param'], $_POST, 'params'
                );
                break;
            case \in_array($this->method, $this->allow, true) && !\count($_POST):
                $this->combineUrlParam();
                $this->setReplace(
                    $this->handle['body'], 
                    file_get_contents('php://input'), 'params'
                );
                $this->patchBody();
                break;
            default:
        }
        return $this;
    }

    private function setUrl($url) {
        $this->getClass('url')
            ? $url = $this->getClass('url')
                : $this->setClass('url', $url);
        $this->setReplace(
            $this->handle['url'], $url, 'url'
        );
        return $this;
    }

    private function patchBody() {
        if (\is_object(json_decode($this->getClass('body')))) {
            \is_array($this->handle['body_patch'])
                ? $patch = $this->handle['body_patch']
                    : $patch = [];
            $body = json_decode($this->getClass('params'), true);
            $this->setClass(
                'params', json_encode(
                    array_replace_recursive($body, $patch)
                )
            );
        }
    }

    private function combineUrlParam() {
        $url_params = null;
        $this->setClass('url_params', $_GET);
        $this->setReplace($this->handle['url_param'], $_GET, 'url_params');
        $this->getClass('url_params') ?: self::$class['url_params'] = [];
        foreach ((array)$this->getClass('url_params') as $key => $value) {
            $url_params .= $key.'='.$value.'&';
        }
        strpos($this->getClass('url'), "\?") && $url_params
            ? $this->setClass('url', $this->getClass('url').'?') : null;
        $this->setClass('url', $this->getClass('url').rtrim($url_params, '&'));
        unset(self::$class['url_params']);
    }

    private function setHeader() {
        if ($this->handle['enable_header']) {
            $this->setClass('headers', Utility::getHeaders());
            $this->setReplace($this->handle['header'], 
                Utility::getHeaders(), 'headers');
        }
        return $this;
    }

    private function setCookie() {
        if ($this->handle['enable_cookie']) {
            $this->setClass('cookies', $_COOKIE);
            $this->setReplace($this->handle['cookie'], 
                $_COOKIE, 'cookies');
        }
        return $this;
    }
}