<?php

namespace Kaleido\Http;

class Encoder extends Worker
{
    public $allow_list = ['post', 'put', 'head', 'options', 'search', 'patch', 'delete'];
    public $method;
    public $action = [];
    public $host;
    public $request_handle = [];
    private static $lock = [];

    /**
     * Encoder constructor.
     * @param $task_id
     * @param $url
     * @throws \ErrorException
     */
    public function __construct($task_id, $url) {
        $this->setTiming();
        $this->_load();
        $this->matchTaskId($task_id);
        $this->check($url);
        $this->handle($task_id, $url);
        $this->lockClass();
    }

    private function check($url) {
        $this->checkHost($url)->checkMethod();
    }

    private function handle($task_id, $url) {
        $this->setTaskId($task_id)->setMethod()
        ->setUrl($url)->setUrlParam()->setUrl($url)
        ->setCookie()->setHeader()->setAction();
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
        if ($this->action['check_hostname']) {
            switch ($this->host) {
                case \is_array($this->host):
                    $host = preg_replace('/^(https?\:\/\/.*?\..*?)\//', '$1', $url);
                    if (!\in_array($host, $this->host, true)) {
                        new HttpException(
                            self::error_message['request_host'], -400
                        );
                    }
                    break;
                case \is_string($this->host):
                    if (!preg_match("/{$this->host}/", $url)) {
                        new HttpException(
                            self::error_message['request_host'], -400
                        );
                    }
                    break;
                default:
            }
        }
        return $this;
    }

    private function checkMethod() {
        switch ($this->method) {
            case \is_string($this->method):
                $method = strtolower($_SERVER['REQUEST_METHOD']);
                if ($method !== $this->method) {
                    new HttpException(
                        self::error_message['request_method'], -400
                    );
                }
                break;
            case \is_array($this->method):
                $method = strtolower($_SERVER['REQUEST_METHOD']);
                if (!\in_array($method, $this->method, true)) {
                    new HttpException(
                        self::error_message['request_method'], -400
                    );
                }
                break;
            default:
        }
        return $this;
    }

    private function setTaskId($task_id) {
        !\is_string($task_id) ?: 
            $this->setClass('task_id', $task_id);
        return $this;
    }

    private function setAction() {
        !\is_array($this->action) ?:
            $this->setClass('action', $this->action);
        return $this;
    }

    private function setMethod() {
        $method = strtolower(
            $_SERVER['REQUEST_METHOD']
        );
        $this->setClass(
            'method', $this->method = $method
        );
        return $this;
    }

    private function setUrlParam() {
        switch ($this->method) {
            case 'get' && $this->action['fix_same_param']:
                $this->setClass(
                    'url', $this->getClass('url').$_SERVER['QUERY_STRING']
                );
                break;
            case 'get':
                $this->setClass('params', $_GET);
                $this->setSort(
                    $this->request_handle['url_param'], $_GET, 'params'
                );
                break;
            case \in_array($this->method, $this->allow_list, true) && \count($_POST):
                $this->combineUrlParam();
                $this->setClass('params', $_POST);
                $this->setSort(
                    $this->request_handle['form_param'], $_POST, 'params'
                );
                break;
            case \in_array($this->method, $this->allow_list, true) && !\count($_POST):
                $this->combineUrlParam();
                $this->setSort(
                    $this->request_handle['body'], 
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
        $this->setSort(
            $this->request_handle['full_url'], $url, 'url'
        );
        return $this;
    }

    private function patchBody() {
        if (\is_object(json_decode($this->getClass('body')))) {
            \is_array($this->request_handle['body_patch'])
                ? $patch = $this->request_handle['body_patch']
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
        $this->setSort($this->request_handle['url_param'], $_GET, 'url_params');
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
        if ($this->action['request_header']) {
            $this->setClass('headers', Utility::getHeaders());
            $this->setSort(
                $this->request_handle['header'], 
                Utility::getHeaders(), 'headers'
            );
        }
        return $this;
    }

    private function setCookie() {
        if ($this->action['request_cookie']) {
            $this->setClass('cookies', $_COOKIE);
            $this->setSort(
                $this->request_handle['cookie'], 
                $_COOKIE, 'cookies'
            );
        }
        return $this;
    }
}