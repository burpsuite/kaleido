<?php

namespace Kaleido\Http;

use LeanCloud\LeanObject;
use LeanCloud\CloudException;
use LeanCloud\Query;

class Capture extends Worker
{
    public static $lock = [];
    public $activity = ['history', 'current'];
    public $leancloud = [];
    public $apiServer;
    public $appId;
    public $appKey;
    public $logType;
    public $masterKey;

    /**
     * Capture constructor.
     * @param $needRecord
     * @param $activity
     * @param $objectId
     * @throws CloudException
     * @throws \ErrorException
     */
    public function __construct($needRecord, $activity = null, $objectId = null) {
        $this->handle($needRecord, $activity, $objectId);
    }

    /**
     * @param $needRecord
     * @param $activity
     * @param $objectId
     * @throws CloudException
     * @throws \ErrorException
     */
    private function handle($needRecord, $activity = null, $objectId = null) {
        switch ($this->logType) {
            case ('leancloud' && $needRecord):
                $this->setTiming('RecTiming');
                $this->getEnv('capture');
                $this->unLogType();
                $lean = new LeanCloud();
                $lean->setClass($this);
                $lean::initialize();
                $init = $lean->leanObject();
                $lean::setRequest();
                $lean::setResponse();
                $lean->set($init, 'response');
                $lean->set($init, 'request');
                $init->save();
                $this->setObjectId($init);
                $this->setTiming('RecTiming');
                break;
            case 'leancloud':
                $this->inActivity($activity);
                $this->getEnv('capture');
                $this->unLogType();
                $lean = new LeanCloud();
                $lean->setClass($this);
                $lean::initialize();
                $init = $lean->leanQuery();
                $this->fetch($lean, $init, $objectId);
                $this->activity($activity);
                break;
        }
    }

    private function unLogType() {
        \is_array($type = $this->{$this->logType}) ?: $type = [];
        foreach ($type as $key => $value) {
            $this->$key = $value;
        }
    }

    private function setObjectId(LeanObject $class) {
        if (\is_string($class->get('objectId'))) {
            \is_array($response = Sender::response(false))
                ?: $response = [];
            !$this->getHandleItem('enable_header')
                ?: header("X-RecId: {$class->get('objectId')}");
        }
    }

    private function getHandleItem($name = null) {
        return !\is_string($name)
            ?: Decoder::getHandle()[$name];
    }

    private function inActivity($activity) {
        \in_array($activity, $this->activity, true)
            ?: new HttpException(
            self::getError('request_activity'), -500
        );
    }

    private function fetch(LeanCloud $class, Query $init, $objectId) {
        $fetch = $class->get($init, $objectId);
        $this->setClass('request', $fetch->get('request'));
        $this->setClass('response', $fetch->get('response'));
    }

    /**
     * @param $activity
     * @throws \ErrorException
     */
    private function activity($activity) {
        switch ($activity) {
            case 'history':
                $this->switchHandle('request');
                $this->setTiming();
                new Decoder($this->getClass('response'));
                break;
            case 'current':
                $this->switchHandle('request');
                $this->setTiming();
                new Sender($this->getClass('request'));
                $this->lockClass();
                new Decoder(Sender::response(false));
                break;
            default:
        }
    }

    private function lockClass() {
        self::$lock = self::$class;
        self::$class = [];
    }
}