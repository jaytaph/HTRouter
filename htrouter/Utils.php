<?php

namespace HTRouter;

/**
 * Additional classes used throughout the system. Most of them would probably have a APR_* equivalent
 */

class Utils {

    /**
     * Validate an encrypted/hashed password. Handles different hash-methods
     * @param $passwd
     * @param $hash
     * @return bool
     * @throws LogicException
     */
    function validatePassword($passwd, $hash) {
        if (substr($hash, 0, 6) == '$apr1$') {
            // Can't do APR's MD5
            throw new \LogicException("Cannot verify APR1 encoded passwords.");
        }

        if (substr($hash, 0, 5) == '{SHA}') {
            // Can't do SHA
            throw new \LogicException("Cannot verify SHA1 encoded password.");
        }

        // It's CRYPT
        $cryptedPassword = crypt($passwd, $hash);
        return ($cryptedPassword == $hash);
    }


    /**
     * Check is source matches destination. Source might be a subnet, partial ip, cidr
     * @param $src
     * @param $dst
     */
    function checkMatchingIP($src, $dst) {
        // Check if it's an IP or partial IP? (hasn't got a . in it)
        if (strpos($src, "/") === false) {
            return $this->_checkMatchingIP_Partial($src, $dst);
        }

        // Check if it's a CIDR or netmask
        list($ip, $postfix) = explode("/", $src, 2);
        if (strpos($postfix, ".")) {
            // Match against a netmask
            return $this->_checkMatchingIP_Netmask($ip, $dst, $postfix);
        } else {
            // Match against cidr
            return $this->_checkMatchingIP_Cidr($ip, $dst, $postfix);
        }

        // Nothing found that matches
        return false;
    }

    /**
     * Matches SRC to DST according to the netmask
     *
     * @param $src
     * @param $dst
     * @param $netmask
     * @return bool
     * @throws UnexpectedValueException
     */
    protected function _checkMatchingIP_Netmask($src, $dst, $netmask) {
        // Is our netmask in a correct IP format?
        if (ip2long($netmask) === false) {
            throw new \UnexpectedValueException("'$netmask' does not look like a subnet mask");
        }

        // Are our ips in a correct IP format?
        if (! $this->isValidIP($src)) {
            throw new \UnexpectedValueException("'$src' does not look like a decent IP");
        }
        if (! $this->isValidIP($dst)) {
            throw new \UnexpectedValueException("'$dst' does not look like a decent IP");
        }


        // Check if netmask masks both the ip's. In that case, both addresses are inside the same submask.
        $nm = ip2long($netmask);

        return ((ip2long($src) & $nm) == (ip2long($dst) & $nm));
    }

    function isValidIP($ip) {
        return (preg_match("/^([1-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/", $ip) >= 1);
    }

    /**
     * Matches $src to $dst, even partials (ie: 10.10 matches 10.10.4.5)
     * @param $src
     * @param $dst
     * @return bool
     */
    protected function _checkMatchingIP_Partial($src, $dst) {
        // Add a . to the end of the ip if it's not a full IP and it doesnt end on a dot.
        // This takes care of matching "allow from 19" with a "192.168.x.x" range address
        if (substr_count($src, ".") != 3 and ($src[strlen($src)-1] != ".")) {
            $src .= ".";
        }

        // This is partial or complete IP, match by string
        if (substr($dst, 0, strlen($src)) == $src) {
            // Found a match (starting with 10.10 inside 10.10.6.5)
            return true;
        }
        return false;
    }

    /**
     * Matches $src to $dst according to the CIDR notation
     *
     * @param $src
     * @param $dst
     * @param $cidr
     * @return bool
     * @throws UnexpectedValueException
     */
    protected function _checkMatchingIP_Cidr($src, $dst, $cidr) {
        if (!is_numeric($cidr) and ($cidr <= 0 or $cidr > 32)) {
            throw new \UnexpectedValueException("'$cidr' does not look like a decent cidr");
        }
        // Are our ips in a correct IP format?
        if (! $this->isValidIP($src)) {
            throw new \UnexpectedValueException("'$src' does not look like a decent IP");
        }
        if (! $this->isValidIP($dst)) {
            throw new \UnexpectedValueException("'$dst' does not look like a decent IP");
        }


        // Convert to binary format
        $bin_src = sprintf("%032b",ip2long($src));
        $bin_dst = sprintf("%032b",ip2long($dst));

        // Compare the number of cidr-bits
        return (substr_compare($bin_src, $bin_dst, 0, $cidr) === 0);
    }



    function checkMatchingHost($src, $dst) {
        // Do a double reverse check
        $dst_name = gethostbyaddr($dst);
        $reversed_dst_ip = gethostbyname($dst_name);

        if (strcmp($dst, $reversed_dst_ip) !== 0) {
            // Reversed IP does not match!
            // @codeCoverageIgnoreStart
            return false;
            // @codeCoverageIgnoreEnd
        }

        // Check if complete string matches
        if (strcmp($src, $dst_name) === 0) {
            // Complete match
            return true;
        }

        // Add a . in front if needed so "apache.org" matches "foo.apache.org", but not "test.fooapache.org"
        if ($src[0] != ".") {
            $src = "." . $src;
        }

        // Check if substring matches
        $matchpart = substr($dst_name, 0-strlen($src));
        if ($matchpart == $src) {
            // Partial match is ok
            return true;
        }

        return false;
    }


    /**
     * Returns true when the URL is - in fact - an URL (the lazy way)
     * @param $url
     * @return bool
     */
    function isUrl($url) {
        return (parse_url($url) !== false);
    }


    function fetchDirectiveFlags($line, $values) {
        $line = strtolower($line);

        if (! in_array($line, array_keys($values))) {
            throw new \UnexpectedValueException("Must be either: ". join(", ", array_keys($values)));
        }

        return $values[$line];
    }

    /**
     * The opposite function of parse_url
     *
     * @param $parsed_url
     * @return string
     */
    function unparse_url($parsed_url) {
        // As found on http://nl3.php.net/manual/en/function.parse-url.php#106731
        $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
        $pass     = ($user || $pass) ? "$pass@" : '';
        $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
        return "$scheme$user$pass$host$port$path$query$fragment";
    }

    function findUriOnDisk(\HTRouter\Request $request, $url) {
        // tries to match the url onto a file on disk. If possible
        $url = parse_url($url);

        $docroot = $request->getDocumentRoot();

        // Strip double slashes if needed
        if ($docroot[strlen($docroot)-1] == "/" && $url['path'][0] == "/") {
            $docroot = substr($docroot, -1);
        }
        $path = $docroot . $url['path'];
        return $path;
    }

    const URI_FILETYPE_MISSING = 0;
    const URI_FILETYPE_DIR     = 1;
    const URI_FILETYPE_FILE    = 2;
    const URI_FILETYPE_OTHER   = 3;

    /**
     * Checks the actual filetype of the file that is mapped onto the URI.
     *
     * @param $url
     * @return int
     */
    function findUriFileType(\HTRouter\Request $request, $url) {
        $path = $this->findUriOnDisk($request, $url);
        if (! is_readable($path)) return self::URI_FILETYPE_MISSING;
        if (is_dir($path)) return self::URI_FILETYPE_DIR;
        if (is_file($path)) return self::URI_FILETYPE_FILE;
        return self::URI_FILETYPE_OTHER;
    }



    /**
     * Status codes as taken from protocol.c 
     */
    protected $_statusLines = array(
        100 => "Continue",
        101 => "Switching Protocols",
        102 => "Processing",

        200 => "OK",
        201 => "Created",
        202 => "Accepted",
        203 => "Non-Authoritative Information",
        204 => "No Content",
        205 => "Reset Content",
        206 => "Partial Content",
        207 => "Multi-Status",

        300 => "Multiple Choices",
        301 => "Moved Permanently",
        302 => "Found",
        303 => "See Other",
        304 => "Not Modified",
        305 => "Use Proxy",
        307 => "Temporary Redirect",

        400 => "Bad Request",
        401 => "Authorization Required",
        402 => "Payment Required",
        403 => "Forbidden",
        404 => "Not Found",
        405 => "Method Not Allowed",
        406 => "Not Acceptable",
        407 => "Proxy Authentication Required",
        408 => "Request Time-out",
        409 => "Conflict",
        410 => "Gone",
        411 => "Length Required",
        412 => "Precondition Failed",
        413 => "Request Entity Too Large",
        414 => "Request-URI Too Large",
        415 => "Unsupported Media Type",
        416 => "Requested Range Not Satisfiable",
        417 => "Expectation Failed",
        422 => "Unprocessable Entity",
        423 => "Locked",
        424 => "Failed Dependency",
        425 => "No code",
        426 => "Upgrade Required",

        500 => "Internal Server Error",
        501 => "Method Not Implemented",
        502 => "Bad Gateway",
        503 => "Service Temporarily Unavailable",
        504 => "Gateway Time-out",
        505 => "HTTP Version Not Supported",
        506 => "Variant Also Negotiates",
        507 => "Insufficient Storage",
        510 => "Not Extended"
    );

    function getStatusLine($status) {
        if (! key_exists($status, $this->_statusLines)) {
            throw new \OutOfBoundsException("Cannot find the statusline for HTTP status $status");
        }
        return $this->_statusLines[$status];
    }


    /**
     * Removes ./ ../ and // from an URI
     *
     * @param $uri
     * @return string
     */
    function getParents($uri) {
        $newDirs = array();
        $dirs = explode("/", $uri);

        $firstElement = true;
        foreach ($dirs as $dir) {
            // empty, but not the first item, so remove it
            if (empty($dir) && ! $firstElement) goto no_emit;

            // Single dot. Same dir, so remove it
            if ($dir == ".") goto no_emit;

            // Double dot in the first entry, so remove it
            if ($dir == ".." && $firstElement) goto no_emit;

            // Double dot and not the first, remove last entry from newdirs
            if ($dir == "..") {
                array_pop($newDirs);    // Remove last items, since we need to pop
                continue;
            }

            // Add this entry to the new structure
            $newDirs[] = $dir;
no_emit:
            $firstElement = false;
        }

        $path =  join("/", $newDirs);
        return $path;
    }


}