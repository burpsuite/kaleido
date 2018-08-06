<?php

namespace Kaleido\Http;

class Utility
{
    public static $errorHeader = ['Host'];
    public static $lostHeader = ['Content-Type'];

    /**
     * Utility::millitime Get microseconds.
     * @return int
     */
    public static function millitime() :int {
        $comps = explode(' ', microtime());
        return (int) sprintf('%d%03d', $comps[1], $comps[0] * 1000);
    }

    /**
     * Utility::bjsonDecode Decode base64 json data.
     * @param $data
     * @param bool $to_array
     * @return mixed
     */
    public static function bjsonDecode($data, $to_array = false) {
        return json_decode(base64_decode($data), $to_array);
    }

    /**
     * Utility::gzbase64Decode Decode base64 gzip data.
     * @param $data
     * @return string
     */
    public static function gzbaseDecode($data) :string {
        return gzdecode(base64_decode($data));
    }

    /**
     * Utility::getHeaders Get all request headers.
     * @return array
     */
    public static function getHeaders() :array {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (!\in_array($name, self::$errorHeader, true)
                && 0 === strpos($name, 'HTTP_')) {
                $headers[str_replace(' ', '-', ucwords(
                    strtolower(str_replace(
                '_', ' ', substr($name, 5)))))] = $value;
            }
        }
        $headers = self::addLostHeader($headers);
        return $headers;
    }

    /**
     * Utility::addLostHeader Fix Lost headers.
     * @param array $headers
     * @return array
     */
    public static function addLostHeader(array $headers) :array {
        exit(print_r($_SERVER));
        foreach (self::$lostHeader as $key => $value) {
            if (!$headers[self::$lostHeader[$key]]) {
                $headers[self::$lostHeader[$key]]
                    = $_SERVER[self::$lostHeader[$value]];
            }
        }
        return $headers;
    }
}