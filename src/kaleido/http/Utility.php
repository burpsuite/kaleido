<?php

namespace Kaleido\Http;

class Utility
{

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
        foreach ((array)$_SERVER as $name => $value) {
            if (0 === strpos($name, 'HTTP_')) {
                $headers[str_replace(' ', '-',
                    ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}