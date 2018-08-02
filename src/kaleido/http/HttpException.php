<?php

namespace Kaleido\Http;

class HttpException extends \RuntimeException
{
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
            getenv(strtoupper('debug'))
             ? error_log("[debug] [kaleido_exception] info:[{$message}] code:[{$code}]") : null;
            exit(json_encode([
                'message' => $message,
                'code' => $code,
                'stack' => getenv(strtoupper('debug'))
                    ? $exception : null
            ]));
        }
    }
}