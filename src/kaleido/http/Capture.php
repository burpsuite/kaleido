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
    public function __construct($needRecord = false) {
        $this->setTiming('RecTiming');
        $this->getEnv('record');
        $this->handle($needRecord);
        $this->setTiming('RecTiming');
    }

    /**
     * @throws CloudException
     */
    private function handle($needRecord) {
        switch ($this->logType) {
            case 'leancloud' && $needRecord:
                $this->unLogType();
                $lean = new LeanCloud();
                $init = $lean->initialize();
                $lean->setClass($this);
                $lean::setRequest();
                $lean::setResponse();
                $lean->set($init, 'response');
                $lean->set($init, 'request');
                $init->save();
                $this->setObjectId($init);
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
}
