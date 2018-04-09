<?php

namespace Kaleido\Http;

use LeanCloud\Client;
use LeanCloud\Object;

class Recorder
{
    const exist_keys = ['app_id', 'app_key', 'master_key', 'server_url', 'record_class'];
    public $env_name = 'kaleido_record';
    public $app_id;
    public $app_key;
    public $master_key;
    public $server_url;
    public $record_class;

    /**
     * Recorder constructor.
     * @param array $request
     * @param array $response
     * @throws \LeanCloud\CloudException
     */
    public function __construct(array $request, array $response) {
        $this->setEnv();
        $this->setTiming($response);
        $this->setRecord($request, $response);
        $this->setTiming($response);
    }

    /**
     * @param $request
     * @param $response
     * @throws \LeanCloud\CloudException
     */
    private function setRecord($request, $response) {
        if (\is_string($this->app_id)) {
            Client::initialize($this->app_id, $this->app_key, $this->master_key);
            Client::setServerUrl($this->server_url);
            $object = new Object($this->record_class);
            $object->set('request', $request);
            $object->set('response', $response);
            $object->save();
            $this->setObjectId($response, $object);
        }
    }

    private function setEnv() {
        if (getenv(strtoupper($this->env_name))) {
            $env = getenv(strtoupper($this->env_name));
            $env_info = Utility::bjsonDecode($env, true);
            foreach ((array)$env_info as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    private function setObjectId($response, Object $object_class) {
        if (\is_array($response) && \is_string($object_class->get('objectId'))) {
            $response['action']['response_header']
                ? header('Kaleido-RecId: '.$object_class->get('objectId'))
                    : null;
        }
    }
 
    private function setTiming($response) {
        if ($response['action']['response_header']) {
            !isset($this->record_timing)
                ? $this->record_timing = Utility::millitime()
                    : $this->record_timing = Utility::millitime() - $this->record_timing;
            header('Kaleido-RecTiming: '.$this->record_timing.'ms');
        }
    }
}