<?php

namespace Kaleido\Http;

use LeanCloud\Client;
use LeanCloud\Query;

class Replay extends Worker
{
    private static $lock;
    public static $body;
    public $activity = ['history', 'current'];
    public $saveType;
    public $saveInfo = [];
    public $appId;
    public $appKey;
    public $masterKey;
    public $endPoint;
    public $className;

    /**
     * Replay constructor.
     * @param $activity
     * @param $objectId
     * @throws \ErrorException
     */
    public function __construct($activity, $objectId) {
        $this->inActivity($activity);
        $this->getEnv('record');
        $this->caseType($objectId);
        $this->handle($activity);
    }

    private function inActivity($activity) {
        \in_array($activity, $this->activity, true)
         ?: new HttpException(
            self::getError('request_activity'), -500
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

    public static function getBody() {
        return self::$class['body'] ?? false;
    }

    /**
     * @param $activity
     * @throws \ErrorException
     */
    private function handle($activity) {
        switch ($activity) {
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