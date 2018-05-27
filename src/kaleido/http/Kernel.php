<?php

namespace Kaleido\Http;

class Kernel
{
    public $route_info = [];

    /**
     * @return mixed
     * @throws \ErrorException
     */
	protected function _load() {
		(new Loader())->loadfile();
		return $this->route_info = json_decode(Loader::fetch(), true);
	}
}