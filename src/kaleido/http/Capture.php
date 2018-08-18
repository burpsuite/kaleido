<?php

namespace Kaleido\Http;

use LeanCloud\LeanObject;
use LeanCloud\CloudException;

class Capture extends Worker
{
    public $leancloud = [];
    public $apiServer;
    public $appId;
    public $appKey;
	public $logType;
    public $masterKey;

    /**
     * Capture constructor.
     * @throws CloudException
     */
    public function __construct() {
        $this->setTiming('RecTiming');
        $this->getEnv('record');
        $this->handle();
        $this->setTiming('RecTiming');
    }

    /**
     * @throws CloudException
     */
    private function handle() {
        switch ($this->logType) {
            case 'leancloud':
                $lean = new LeanCloud();
                $lean->setClass($this);
                $lean::setRequest();
                $lean::setResponse();
                $this->switchType();
                $this->setCapture();
                break;
        }
    }

    private function switchType() {
        \is_array($type = $this->{$this->logType}) ?: $type = [];
        foreach ($type as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * @throws CloudException
     */
    private function setCapture() {
        switch ($this->logType) {
            case 'leancloud':
                $lean = new LeanCloud();
                $init = $lean->initialize();
                $lean->set($init, 'response');
                $lean->set($init, 'request');
                $init->save();
                $this->setObjectId($init);
                break;
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
}