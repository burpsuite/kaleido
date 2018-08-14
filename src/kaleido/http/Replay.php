<?php

namespace Kaleido\Http;

use LeanCloud\Client;
use LeanCloud\Query;

class Replay extends Worker
{
    public $action = ['history', 'current'];
    private static $lock;
    public static $body;
    public $saveType;
    public $saveInfo = [];
    public $appId;
    public $appKey;
    public $masterKey;
    public $endPoint;
    public $className;

    /**
     * Replay constructor.
     * @param $action
     * @param $objectId
     * @throws \ErrorException
     */
    public function __construct($action, $objectId) {
        $this->checkAction($action);
        $this->getEnv('record');
        $this->caseType($objectId);
        $this->handle($action);
    }

    private function checkAction($action) {
        \in_array($action, $this->action, true)
         ?: new HttpException(
            self::getError('request_action'), -500
        );
    }

    private function caseType($objectId) {
        switch ($this->saveType) {
            case 'leancloud':
                $this->switchType();
                $this->initialize();
                $this->fetch($objectId);
                break;
        }
    }

    private function switchType() {
        $type = $this->saveInfo[$this->saveType];
        \is_array($type) ?: $type = [];
        foreach ($type as $key => $value) {
            $this->$key = $value;
        }
    }

    private function initialize() {
        switch($this->saveType) {
            case 'leancloud':
                Client::initialize($this->appId,
                    $this->appKey, $this->masterKey);
                Client::setServerUrl($this->endPoint);
                break;
        }
    }

    private function fetch($objectId) {
        switch ($this->saveType) {
            case 'leancloud':
                $query = new Query($this->className);
                $fetch = $query->get($objectId);
                $this->setClass('request',
                    $fetch->get('request'));
                $this->setClass('response',
                    $fetch->get('response'));
                break;
        }
    }

    private function lockClass() {
        self::$lock = self::$class;
        self::$class = [];
    }

    /**
     * @param $action
     * @throws \ErrorException
     */
    private function handle($action) {
        switch ($action) {
            case 'history':
                $this->switchHandle('request');
                $this->setTiming();
                new Decoder($this->getClass('response'));
                $this->setClass('body', Decoder::getBody());
                break;
            case 'current':
                $this->switchHandle('request');
                $this->setTiming();
                new Sender($this->getClass('request'));
                $this->lockClass();
                new Decoder(Sender::response(false));
                $this->setClass('body', Decoder::getBody());
                break;
            default:
        }
    }
}