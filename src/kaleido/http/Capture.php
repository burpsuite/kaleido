<?php

namespace Kaleido\Http;

use LeanCloud\LeanObject;
use LeanCloud\CloudException;
use LeanCloud\Query;

class Capture extends Worker
{
    public $activity = ['history', 'current'];
    public $leancloud = [];
    public $apiServer;
    public $appId;
    public $appKey;
    public $capType;
    public $masterKey;

    /**
     * Capture constructor.
     * @param $activity
     * @param $objectId
     * @throws CloudException
     * @throws \ErrorException
     */
    public function __construct($activity = null, $objectId = null) {
        $this->setTiming('Save-Timing');
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
        switch ($this->capType) {
            case $this->capType === 'leancloud' && null === $activity:
                $this->unpackItem($this->capType);
                $lean = new LeanCloud();
                $lean->setClass($this);
                $lean::initialize();
                $init = $lean->leanObject();
                $lean->setRequest($init);
                $lean->setResponse($init);
                $init->save();
                $this->setObjectId($init);
                $this->setTiming('Save-Timing');
                break;
            case $this->capType === 'leancloud' && null !== $activity:
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
            \is_array($response = Sender::response(false)) ?: $response = [];
            $this->handle['enable_header'] = Decoder::getHandle('enable_header');
            !Decoder::getHandle('enable_header') ?: header("X-Object-Id: {$class->get('objectId')}");
        }
    }

    private function inActivity($activity) {
        \in_array($activity, $this->activity, true)
            ?: new HttpException(self::getError('request_activity'), -500);
    }

    private function fetch(LeanCloud $class, Query $init, $objectId) {
        parent::setItem('request', $class->get($init, $objectId)->get('request'));
        parent::setItem('response', $class->get($init, $objectId)->get('response'));
    }

    /**
     * @param $activity
     * @throws \ErrorException
     */
    private function activity($activity = null) {
        if ($activity === 'history') {
            $this->switchHandle('request');
            $this->setTiming();
            new Decoder(parent::getItem('response'));
        } elseif ($activity === 'current') {
            $this->switchHandle('request');
            $this->setTiming();
            new Sender(parent::getItem('request'));
            parent::lockItem();
            new Decoder(Sender::response(false));
        }
    }
}