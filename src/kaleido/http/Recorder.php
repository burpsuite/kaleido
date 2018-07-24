<?php

namespace Kaleido\Http;

use LeanCloud\Client;
use LeanCloud\Object;

class Recorder extends Worker
{
    public $app_id;
    public $app_key;
    public $master_key;
    public $server;

    /**
     * Recorder constructor.
     * @param array $request
     * @param array $response
     * @throws \LeanCloud\CloudException
     */
    public function __construct(array $request, array $response) {
        $this->setTiming('RecTiming');
        $this->getEnv(__CLASS__);
        $this->setRecord($request, $response);
        $this->setTiming('RecTiming');
    }

    /**
     * @param $request
     * @param $response
     * @throws \LeanCloud\CloudException
     */
    private function setRecord($request, $response) {
        if (\is_string($this->app_id)) {
            Client::initialize($this->app_id, 
                $this->app_key, $this->master_key);
            Client::setServerUrl($this->server);
            $object = new Object($this->class);
            $object->set('request', $request);
            $object->set('response', $response);
            $object->save();
            $this->setObjectId($response, $object);
        }
    }

    private function setObjectId($response, Object $object_class) {
        if (\is_array($response) && \is_string($object_class->get('objectId'))) {
            $this->control = $response['control'];
            $response['control']['response_header']
                ? header("X-RecId: {$object_class->get('objectId')}") : null;
        }
    }
}