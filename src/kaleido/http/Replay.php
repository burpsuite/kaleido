<?php

namespace Kaleido\Http;

use LeanCloud\Client;
use LeanCloud\Query;

class Replay
{
    public static $body;
    public $env_name = 'kaleido_record';
    public $app_id;
    public $app_key;
    public $master_key;
    public $server_url;
    public $record_class;
    public $request;
    public $response;
    public $action = ['history', 'current'];

    /**
     * Replay constructor.
     * @param $action
     * @param $object_id
     * @throws \ErrorException
     */
    public function __construct($action, $object_id) {
        $this->check($action, $object_id);
        $this->setEnv();
        $this->handle($action, $object_id);
    }

    private function check($action, $object_id) {
        $this->checkAction($action);
        $this->checkObjectId($object_id);
    }

    private function setEnv() {
        if (getenv(strtoupper($this->env_name))) {
            $env = getenv(strtoupper($this->env_name));
            $env_info = Utility::bjsonDecode($env, true);
            foreach ((array)$env_info as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    public static function getBody() {
        return self::$body;
    }

    /**
     * @param $action
     * @param $object_id
     * @throws \ErrorException
     */
    private function handle($action, $object_id) {
        $this->getObject($object_id);
        switch ($action) {
            case 'history':
                new Decoder($this->response);
                self::$body = Decoder::getBody();
                break;
            case 'current':
                new Sender($this->request);
                new Decoder(Sender::response(false));
                self::$body = Decoder::getBody();
                break;
            default:
        }
    }

    private function checkAction($action) {
        if (!\in_array($action, $this->action, true)) {
            new HttpException(
                'the request_action is not in kaleido::action.',
                -500
            );
        }
    }

    private function checkObjectId($object_id) {
        Utility::isString(
            $object_id,
            'object_id is a non-string type.',
            -500
        );
    }

    private function getObject($object_id) {
        if (\is_string($this->app_id)) {
            Client::initialize($this->app_id, $this->app_key, $this->master_key);
            Client::setServerUrl($this->server_url);
            $query = new Query($this->record_class);
            $fetch = $query->get($object_id);
            $this->request = $fetch->get('request');
            $this->response = $fetch->get('response');
        }
    }
}