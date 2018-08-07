<?php

namespace Kaleido\Http;

use LeanCloud\Client;
use LeanCloud\CloudException;
use LeanCloud\LeanObject;

class Recorder extends Worker
{
    public $masterKey;
    public $saveType;
    public $saveInfo;
    public $appKey;
    public $appId;
    public $endPoint;
    public $className;

    /**
     * Recorder constructor.
     * @param array $request
     * @param array $response
     */
    public function __construct(array $request, array $response) {
        $this->getHandle();
        $this->setTiming('RecTiming');
        $this->getEnv('record');
        $this->caseType($request, $response);
        $this->setTiming('RecTiming');
    }

    private function getHandle() {
        exit(print_r(Decoder::class(false)));
        if (Decoder::class(false)) {
            $this->handle =
                Decoder::class(false)['handle'];
        }
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
                        self::getError('save_exception'), -500
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
                Client::setServerUrl(
                $this->endPoint);
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
                $object = new LeanObject($this->className);
                $object->set('request', $request);
                $object->set('response', $response);
                $object->save();
                $this->setObjectId($response, $object);
                break;
        }
    }

    private function setObjectId($response, LeanObject $class) {
        if (\is_string($class->get('objectId'))) {
            \is_array($response) ?: $response = [];
            !$this->handle['enable_header']
                ?: header("X-RecId: {$class->get('objectId')}");
        }
    }
}