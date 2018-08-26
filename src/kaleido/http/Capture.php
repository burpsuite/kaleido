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
     * @param $activity
     * @param $objectId
     * @throws CloudException
     * @throws \ErrorException
     */
    public function __construct($activity = null, $objectId = null) {
        $this->setTiming('RecTiming');
        $this->getEnv('capture');
        $this->handle($activity, $objectId);
    }

    /**
     * @param $activity
     * @param $objectId
     * @throws CloudException
     * @throws \ErrorException
     */
    private function handle($activity = null, $objectId = null) {
        switch ($this->logType) {
            case $this->logType === 'leancloud' && $activity === null:
                $this->unpackItem($this->logType);
                $lean = new LeanCloud();
                $lean->setClass($this);
                $lean::initialize();
                $init = $lean->leanObject();
                $lean::setRequest();
                $lean::setResponse();
                $lean->set($init, 'request');
                $lean->set($init, 'response');
                $init->save();
                $this->setObjectId($init);
                $this->setTiming('RecTiming');
                break;
            case $this->logType === 'leancloud' && $activity !== null:
                $this->inActivity($activity);
                $this->unpackItem($this->logType);
                $lean = new LeanCloud();
                $lean->setClass($this);
                $lean::initialize();
                $init = $lean->leanQuery();
                $this->fetch($lean, $init, $objectId);
                $this->activity($activity);
                break;
        }
    }

    private function setObjectId(LeanObject $class) {
        if (\is_string($class->get('objectId'))) {
            \is_array($response = Sender::response(false))
                ?: $response = [];
            $this->handle['enable_header']
                 = $this->getHandleItem('enable_header');
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
        parent::setClass('request', $fetch->get('request'));
        parent::setClass('response', $fetch->get('response'));
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
                new Decoder(parent::getClass('response'));
                break;
            case 'current':
                $this->switchHandle('request');
                $this->setTiming();
                new Sender(parent::getClass('request'));
                $this->lockClass();
                new Decoder(Sender::response(false));
                break;
            default:
        }
    }

    private function lockClass() {
        self::$lock = self::$class;
        parent::resetClass();
    }
}