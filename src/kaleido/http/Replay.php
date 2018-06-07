<?php

namespace Kaleido\Http;

use LeanCloud\Client;
use LeanCloud\Query;

class Replay extends Worker
{
    public $action = ['history', 'current'];
    public static $body;
    private static $lock;
    public $app_id;
    public $app_key;
    public $master_key;
    public $server;
    public $request;
    public $response;

    /**
     * Replay constructor.
     * @param $action
     * @param $object_id
     * @throws \ErrorException
     */
    public function __construct($action, $object_id) {
        $this->check($action, $object_id);
        $this->getEnv('record');
        $this->getObject($object_id);
        $this->handle($action, $object_id);
    }

    private function check($action, $object_id) {
        $this->checkAction($action);
        $this->checkObjectId($object_id);
    }

    private function lockClass() {
        self::$lock = self::$class;
        self::$class = [];
    }

    public static function getBody() {
        return parent::getBody();
    }

    /**
     * @param $action
     * @param $object_id
     * @throws \ErrorException
     */
    private function handle($action, $object_id) {
        switch ($action) {
            case 'history':
                $this->setTiming();
                new Decoder($this->getClass('response'));
                $this->setClass(
                    'body', Decoder::getBody()
                );
                break;
            case 'current':
                $this->setTiming();
                $request = $this->getClass('request');
                $this->lockClass();
                new Sender($request);
                new Decoder(Sender::response(false));
                $this->setClass(
                    'body', Decoder::getBody()
                );
                break;
            default:
        }
    }

    private function checkAction($action) {
        \in_array($action, $this->action, true)
         ?: new HttpException(
            self::error_message['request_action'], -500
        );
    }

    private function checkObjectId($object_id) {
        \is_string($object_id) 
        ?: new HttpException(
            self::error_message['object_id'], -500
        );
    }

    private function getObject($object_id) {
        Client::initialize(
            $this->app_id, $this->app_key, $this->master_key
        );
        Client::setServerUrl($this->server);
        $fetch = (new Query($this->class))->get($object_id);
        $this->setClass('request', $fetch->get('request'));
        $this->setClass('response', $fetch->get('response'));
    }
}