<?php
/**
 * Generic functionality
 */

if (! function_exists("apache_request_headers")) {

    /**
     * @return array
     */
    function apache_request_headers() {
        $headers = array();

        foreach ($_SERVER as $key => $item) {
            if (! is_string($key)) continue;
            if (substr($key, 0, 5) != "HTTP_") continue;

            $key = substr($key, 5);
            $key = strtolower($key);
            $key = str_replace("_", "-", $key);

            $key = preg_replace_callback("/^(.)|-(.)/", function ($matches) { return strtoupper($matches[0]); }, $key);
            $headers[$key] = $item;
        }

        return $headers;
    }

}
