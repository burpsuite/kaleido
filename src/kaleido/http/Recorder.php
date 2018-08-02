<?php

namespace Kaleido\Http;

use LeanCloud\Client;
use LeanCloud\CloudException;
use LeanCloud\Object;

class Recorder extends Worker
{
    public $className;
    public $appId;
    public $appKey;
    public $masterKey;
    public $endPoint;
    public $saveType;
    public $saveInfo;

    /**
     * Recorder constructor.
     * @param array $request
     * @param array $response
     */
    public function __construct(array $request, array $response) {
        $this->setTiming('RecTiming');
        $this->getEnv('record');
        $this->caseType($request, $response);
        $this->setTiming('RecTiming');
    }

    private function caseType($request, $response) {
        switch ($this->saveType) {
            case 'leancloud':
                $this->switchType();
                $this->initialize();
                try {
                    $this->saveRecord($request, $response);
                } catch (CloudException $exception) {
                    new HttpException(
                        self::error['save_exception'], -500
                    );
                }
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

    /**
     * @param $request
     * @param $response
     * @throws \LeanCloud\CloudException
     */
    private function saveRecord($request, $response) {
        switch ($this->saveType) {
            case 'leancloud':
                $object = new Object($this->className);
                $object->set('request', $request);
                $object->set('response', $response);
                $object->save();
                $this->setObjectId($response, $object);
                break;
        }
    }

    private function setObjectId($response, Object $class) {
        if (\is_string($class->get('objectId'))) {
            \is_array($response) ?: $response = [];
            $header = $response['handle']['enable_header'];
            $header ? header(
                "X-RecId: {$class->get('objectId')}") : null;
        }
    }
}