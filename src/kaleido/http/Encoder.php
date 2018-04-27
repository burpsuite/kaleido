<?php

namespace Kaleido\Http;

class Encoder
{
    public $allow_list = ['post', 'put', 'head', 'options', 'search', 'patch', 'delete'];
    public $route_info = [];
    public $method;
    public $action = [];
    public $host;
    public $request_handle = [];
    public static $payload = [];

    /**
     * Encoder constructor.
     * @param $task_id
     * @param $full_url
     * @throws \ErrorException
     */
    public function __construct($task_id, $full_url) {
        $this->_load();
        $this->matchTaskId($task_id);
        $this->check($full_url);
        $this->handle(
            $task_id,
            $full_url
        );
    }

    /**
     * @throws \ErrorException
     */
    private function _load() {
        (new Loader())->loadfile();
        return $this->route_info = json_decode(Loader::fetch(), true);
    }

    private function check($full_url) {
        $this->checkHost($full_url);
        $this->checkMethod();
    }

    private function handle($task_id, $full_url) {
        $this->setTaskId($task_id);
        $this->setMethod();
        $this->setUrl($full_url);
        $this->setUrlParam();
        $this->setUrl($full_url);
        $this->setCookie();
        $this->setHeader();
        $this->setAction();
    }

    public static function payload($encode) {
        return $encode ? json_encode(self::$payload)
            : self::$payload;
    }

    private function matchTaskId($task_id) {
        if (array_key_exists($task_id, $this->route_info)) {
            foreach ((array)$this->route_info[$task_id] as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    private function checkHost($full_url) {
        if ($this->action['check_hostname']) {
            switch ($this->host) {
                case \is_array($this->host):
                    $host = preg_replace('/^((http|https)\:\/\/.*?\..*?)\/.*/', '$1', $full_url);
                    if (!\in_array($host, $this->host, true)) {
                        new HttpException(
                            'the request_host and kaleido configuration do not match.',
                            -400
                        );
                    }
                    break;
                case \is_string($this->host):
                    if (!preg_match("/{$this->host}/", $full_url)) {
                        new HttpException(
                            'the request_host and kaleido configuration do not match.',
                            -400
                        );
                    }
                    break;
                default:
            }
        }
    }

    private function checkMethod() {
        switch ($this->method) {
            case \is_string($this->method):
                if (strtolower($_SERVER['REQUEST_METHOD']) !== $this->method) {
                    new HttpException(
                        'the request_method and kaleido do not match.',
                        -400
                    );
                }
                break;
            case \is_array($this->method):
                if (!\in_array(strtolower($_SERVER['REQUEST_METHOD']), $this->method, true)) {
                    new HttpException(
                        'the request_method and kaleido do not match.',
                        -400
                    );
                }
                break;
            default:
        }
    }

    private function getPayload($name = 'null') {
        return self::$payload[$name] ?? null;
    }

    private function setPayload($name, $value) {
        \is_string($name) ?: $name = 'null';
        switch ($name) {
            case \is_array($value) && !\count($value):
                self::$payload[$name] = null;
                break;
            case null === $value:
                unset(self::$payload[$name]);
                break;
            default:
                self::$payload[$name] = $value;
                break;
        }
    }

    private function setTaskId($task_id) {
        if (\is_string($task_id)) {
            $this->setPayload('task_id', $task_id);
        }
    }

    private function setAction() {
        if (\is_array($this->action)) {
            $this->setPayload('action', $this->action);
        }
    }

    private function setMethod() {
        $method = strtolower($_SERVER['REQUEST_METHOD']);
        $this->method = $method;
        $this->setPayload('method', $method);
    }

    private function setUrlParam() {
        switch ($this->method) {
            case 'get' && $this->action['fix_same_param']:
                $this->setPayload('url', self::$payload['url'].$_SERVER['QUERY_STRING']);
                break;
            case 'get':
                $this->setPayload('params', $_GET);
                $this->setSort(
                    $this->request_handle['url_param'],
                    $_GET,
                    'params'
                );
                break;
            case \in_array($this->method, $this->allow_list, true) && \count($_POST) > 0:
                $this->combineUrlParam();
                $this->setPayload('params', $_POST);
                $this->setSort(
                    $this->request_handle['form_param'],
                    $_POST,
                    'params'
                );
                break;
            case \in_array($this->method, $this->allow_list, true) && !\count($_POST):
                $this->combineUrlParam();
                $this->setSort(
                    $this->request_handle['body'],
                    file_get_contents('php://input'),
                    'params'
                );
                $this->patchBody();
                break;
            default:
        }
    }

    private function setUrl($full_url) {
        if (null === $this->getPayload('url')) {
            $this->setPayload('url', $full_url);
        }
        $this->getPayload('url')
            ? $full_url = $this->getPayload('url')
                : false;
        $this->setSort(
            $this->request_handle['full_url'],
            $full_url,
            'url'
        );
    }

    private function patchBody() {
        if (\is_array(json_decode($this->getPayload('body'), true))) {
            \is_array($this->request_handle['body_patch'])
                ? $patch = $this->request_handle['body_patch']
                    : $patch = [];
            $body = json_decode(
                $this->getPayload('params'),
                true
            );
            $this->setPayload('params',
                json_encode(array_replace_recursive(
                    $body,
                    $patch
                ))
            );
        }
    }

    private function combineUrlParam() {
        $url_params = null;
        $this->setPayload('url_params', $_GET);
        $this->setSort($this->request_handle['url_param'], $_GET, 'url_params');
        $this->getPayload('url_params') !== null ?: self::$payload['url_params'] = [];
        foreach ((array)$this->getPayload('url_params') as $key => $value) {
            $url_params .= $key.'='.$value.'&';
        }
        false !== strpos($this->getPayload('url'), "\?") && $url_params
            ? $this->setPayload('url', $this->getPayload('url').'?') : null;
        $this->setPayload('url', $this->getPayload('url').rtrim($url_params, '&'));
        unset(self::$payload['url_params']);
    }

    private function setHeader() {
        if ($this->action['request_header']) {
            $this->setPayload('headers', Utility::getHeaders());
            $this->setSort(
                $this->request_handle['header'], 
                Utility::getHeaders(), 
                'headers'
            );
        }
    }

    private function setCookie() {
        if ($this->action['request_cookie']) {
            $this->setPayload('cookies', $_COOKIE);
            $this->setSort(
                $this->request_handle['cookie'], 
                $_COOKIE, 
                'cookies'
            );
        }
    }

    /**
     * @param array $rep_list
     * @param $subject
     * @param string $save_name
     */
    private function setSort(array $rep_list, $subject, $save_name = 'null') {
        if (!\count($rep_list)) {
            $this->setPayload($save_name, $subject);
        }
        foreach ($rep_list as $key => $value) {
            switch ($rep_list) {
                case \is_array($subject):
                    self::$payload[$save_name][$key] = $value;
                    if (!$value) {
                        unset(self::$payload[$save_name][$key]);
                    }
                    break;
                case \is_string($subject):
                    $filter_res = preg_replace("/{$key}/", $value, $subject);
                    $this->setPayload($save_name, $filter_res);
                    break;
                default:
            }
        }
    }
}