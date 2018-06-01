<?php

namespace Kaleido\Http;

class HttpException extends \RuntimeException
{
	public $env_name = 'burpsuite_debug';

	/**
	 * HttpException constructor.
	 * @param string $message
	 * @param int $code
	 */
	public function __construct($message = 'null', $code = 0) {
		parent::__construct($message, $code);
		try {
			throw new \RuntimeException(null);
		} catch (\RuntimeException $exception) {
			getenv(strtoupper($this->env_name)) ?
				error_log("[debug] [kaleido_exception] message:[{$message}] code:[{$code}]") : null;
			exit(json_encode([
				'message' => $message,
				'code' => $code,
				'stack' => getenv(strtoupper($this->env_name))
					? (array) $exception : false
			]));
		}
	}
}