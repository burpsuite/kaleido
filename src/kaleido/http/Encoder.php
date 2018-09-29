<?php

namespace Kaleido\Http;

class Encoder extends Worker
{
    public $allow = ['post', 'put', 'head', 'options', 'search', 'patch', 'delete'];
    public $method;
    public $host;

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
        parent::lockItem(__CLASS__);
    }

    private function check($url = null) {
        $this->switchHandle('request');
        $this->checkHost($url)->checkMethod();
    }

    private function handle($taskId, $url = null) {
        $this->setTaskId($taskId)->setMethod()
        ->setUrl($url)->setUrlParam()->setUrl($url)
        ->setCookie()->setHeader()->setMaxSize()
        ->setHost($url)->setProtocol($url);
    }

    public static function payload($encode) {
        return $encode ? json_encode(parent::$lock[__CLASS__])
            : (array)parent::$lock[__CLASS__];
    }

    private function setHost($url = null) {
        parent::setItem('host', parent::getUrlInfo($url, 'host'));
        return $this;
    }

    private function setProtocol($url = null) {
        parent::setItem('protocol', parent::getUrlInfo($url, 'protocol'));
        return $this;
    }

    private function checkHost($url = null) {
        if ($this->handle['check_hostname']) {
            !\is_string($this->host) ?: $this->host = [$this->host];
            if (!\in_array(parent::getUrlInfo($url, 'host'), $this->host, true)) {
                new HttpException(self::getError('request_host'), -400);
            }
        }
        return $this;
    }

    private function checkMethod() {
        if ($this->handle['check_method']) {
            !\is_string($this->method) ?: $this->method = [$this->method];
            if (!\in_array(strtolower($_SERVER['REQUEST_METHOD']), $this->method, true)) {
                new HttpException(self::getError('request_method'), -400);
            }
        }
        return $this;
    }

    private function setMaxSize() {
        if (\is_int($this->handle['maxSize'])) {
            parent::setItem('maxSize', 
                $this->handle['maxSize']);
        }
        return $this;
    }

    private function setTaskId($taskId) {
        if (\is_string($taskId)) {
            parent::setItem('taskId', $taskId);
        }
        return $this;
    }

    private function setMethod() {
        parent::setItem('method', $this->method = strtolower($_SERVER['REQUEST_METHOD']));
        return $this;
    }

    private function setUrlParam() {
        switch ($this->method) {
            case 'get' === $this->method && $this->handle['fix_urlencode']:
                parent::setItem('url', parent::getItem('url') . $_SERVER['QUERY_STRING']);
                break;
            case 'get' === $this->method:
                parent::setItem('params', $_GET);
                $this->setReplace($this->handle['url_param'], $_GET, 'params');
                break;
            case \in_array($this->method, $this->allow, true) && \count($_POST) > 1:
                $this->combineUrlQuery();
                parent::setItem('params', $_POST);
                $this->setReplace($this->handle['form_param'], $_POST, 'params');
                break;
            case \in_array($this->method, $this->allow, true) && !\count($_POST):
                $this->combineUrlQuery();
                $this->setReplace($this->handle['body'], file_get_contents('php://input'), 'params');
                $this->patchBody();
                break;
        }
        return $this;
    }

    private function setUrl($url = null) {
        parent::getItem('url') ? $url = parent::getItem('url') : parent::setItem('url', $url);
        $this->setReplace($this->handle['url'], $url, 'url');
        return $this;
    }

    private function patchBody() {
        if (\is_object(json_decode(parent::getItem('body')))) {
            \is_array($this->handle['body_patch'])
                ? $patch = $this->handle['body_patch']
                    : $patch = [];
            $body = json_decode(parent::getItem('params'), true);
            parent::setItem('params', json_encode(
                    array_replace_recursive($body, $patch)
                )
            );
        }
    }

    private function combineUrlQuery() {
        parent::setItem('url_params', $_GET);
        $this->setReplace($this->handle['url_param'],
            parent::getItem('url_params'), 'url_params');
        $params = http_build_query(parent::getItem('url_params') ?? []);
        !$params || strpos(parent::getItem('url'), '?', -1)
            ?: parent::setItem('url', parent::getItem('url').'?');
        parent::setItem('url', parent::getItem('url').$params);
        unset(parent::$item['url_params']);
    }

    private function setHeader() {
        if ($this->handle['enable_header']) {
            parent::setItem('headers', Utility::getHeaders());
            $this->setReplace($this->handle['header'], 
                Utility::getHeaders(), 'headers');
        }
        return $this;
    }

    private function setCookie() {
        if ($this->handle['enable_cookie']) {
            parent::setItem('cookies', $_COOKIE);
            $this->setReplace($this->handle['cookie'], 
                $_COOKIE, 'cookies');
        }
        return $this;
    }
}