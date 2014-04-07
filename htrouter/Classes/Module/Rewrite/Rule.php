<?php

namespace HTRouter\Module\Rewrite;

use HTRouter\Module\Rewrite\Condition;
use HTRouter\Module\Rewrite\Flag;

class Result {
    public $vary;
    public $rc;
}

class Rule {
    const TYPE_PATTERN_UNKNOWN = 0;

    const TYPE_SUB_UNKNOWN   = 0;
    const TYPE_SUB           = 1;
//    const TYPE_SUB_FILE_PATH = 1;
//    const TYPE_SUB_URL_PATH  = 2;
//    const TYPE_SUB_ABS_URL   = 3;
    const TYPE_SUB_NONE      = 4;

    protected $_match = false;                // True is rule matches, false otherwise.

    protected $_conditions = array();        // All rewrite conditions in order

    protected $_request;

    protected $_ruleMatches = array();
    protected $_condMatches = array();

    function __construct($pattern, $substitution, $flags) {
        // Set default values
        $this->_pattern = $pattern;
        $this->_patternNegate = false;

        $this->_substitution = $substitution;
        $this->_substitutionType = self::TYPE_SUB_UNKNOWN;

        $this->_flags = array();

        $this->_parsePattern($pattern);
        $this->_parseSubstitution($substitution);
        $this->_parseFlags($flags);
    }

    /**
     * @return \HTRouter\Request
     */
    function getRequest() {
        return $this->_request;
    }

    function __toString() {
        $ret = $this->_pattern." ".$this->_substitution;
        if (count($this->_flags)) $ret .= " [".join(", ", $this->_flags)."]";
        return $ret;
    }

    /**
     * Add a new condition to the list
     *
     * @param Condition $condition
     */
    public function addCondition(Condition $condition) {
//        // We need this, since it's possible we need to do a back-reference to the rule from inside a condition
//        $condition->linkRule($this);

        // Add condition
        $this->_conditions[] = $condition;
    }

    public function getConditions() {
        return $this->_conditions;
    }

    protected function _parsePattern($pattern) {
        if ($pattern[0] == "!") {
            $this->_patternNegate = true;
            $this->_pattern = substr($pattern, 1);
        } else {
            $this->_pattern = $pattern;
        }
    }

    protected function _parseSubstitution($substitution) {
        if ($substitution == "-") {
            $this->_substitutionType = self::TYPE_SUB_NONE;
            $this->_substitution = $substitution;
        } else {
            $this->_substitutionType = self::TYPE_SUB;
            $this->_substitution = $substitution;
        }
    }

    protected function _parseFlags($flags) {
        $flags = trim($flags);
        if (empty($flags)) return;

        // Check for brackets
        if ($flags[0] != '[' && $flags[strlen($flags)-1] != ']') {
            throw new \InvalidArgumentException("Flags must be bracketed");
        }

        // Remove brackets
        $flags = substr($flags, 1, -1);

        foreach (explode(",",$flags) as $flag) {
            $flag = trim($flag);
            $key = null;
            $value = null;

            // Remove value if found (ie: cookie=TEST:VALUE)
            if (strpos($flag, '=') !== false) {
                list($flag, $value) = explode("=", $flag, 2);

                if (strpos($value, ':')) {
                    list($key, $value) = explode(":", $value, 2);
                }
            }

            switch (strtolower($flag)) {
                case "b" :
                    $this->_flags[] = new Flag(Flag::TYPE_BEFORE, $key, $value);
                    break;
                case "chain" :
                case "c" :
                    $this->_flags[] = new Flag(Flag::TYPE_CHAIN, $key, $value);
                    break;
                case "cookie" :
                case "co" :
                    $this->_flags[] = new Flag(Flag::TYPE_COOKIE, $key, $value);
                    break;
                case "discardpath" :
                case "dpi" :
                    $this->_flags[] = new Flag(Flag::TYPE_DISCARDPATH, $key, $value);
                    break;
                case "env" :
                case "e" :
                    $this->_flags[] = new Flag(Flag::TYPE_ENV, $key, $value);
                    break;
                case "forbidden" :
                case "f" :
                    $this->_flags[] = new Flag(Flag::TYPE_FORBIDDEN, $key, $value);
                    break;
                case "gone" :
                case "g" :
                    $this->_flags[] = new Flag(Flag::TYPE_GONE, $key, $value);
                    break;
                case "handler" :
                case "h" :
                    $this->_flags[] = new Flag(Flag::TYPE_HANDLER, $key, $value);
                    break;
                case "last" :
                case "l" :
                    $this->_flags[] = new Flag(Flag::TYPE_LAST, $key, $value);
                    break;
                case "next" :
                case "n" :
                    $this->_flags[] = new Flag(Flag::TYPE_NEXT, $key, $value);
                    break;
                case "nocase" :
                case "nc" :
                    $this->_flags[] = new Flag(Flag::TYPE_NOCASE, $key, $value);
                    break;
                case "noescape" :
                case "ne" :
                    $this->_flags[] = new Flag(Flag::TYPE_NOESCAPE, $key, $value);
                    break;
                case "nosubreqs" :
                case "ns" :
                    $this->_flags[] = new Flag(Flag::TYPE_NOSUBREQS, $key, $value);
                    break;
                case "proxy" :
                case "p" :
                    $this->_flags[] = new Flag(Flag::TYPE_PROXY, $key, $value);
                    break;
                case "passthrough" :
                case "pt" :
                    $this->_flags[] = new Flag(Flag::TYPE_PASSTHROUGH, $key, $value);
                    break;
                case "qsappend" :
                case "qsa" :
                    $this->_flags[] = new Flag(Flag::TYPE_QSA, $key, $value);
                    break;
                case "redirect" :
                case "r" :
                    $this->_flags[] = new Flag(Flag::TYPE_REDIRECT, $key, $value);
                    break;
                case "skip" :
                case "s" :
                    $this->_flags[] = new Flag(Flag::TYPE_SKIP, $key, $value);
                    break;
                case "type" :
                case "t" :
                    $this->_flags[] = new Flag(Flag::TYPE_MIMETYPE, $key, $value);
                    break;
                default :
                    throw new \InvalidArgumentException("Unknown flag found in rewriterule");
                    break;
            }

        }
    }

    function hasFlag($type) {
        return ($this->getFlag($type) != null);
    }

    /**
     * @param $type string
     * @return Flag|null
     */
    function getFlag($type) {
        foreach ($this->getFlags() as $flag) {
            /**
             * @var $flag \HTRouter\Module\Rewrite\Flag
             */
            if ($flag->getType() == $type) {
                return $flag;
            }
        }
        return null;
    }

    /**
     * @return array
     */
    function getFlags() {
        return $this->_flags;
    }

//    protected function _checkMatch() {
//        // Returns true if the rule match, false otherwise
//        $match = true;
//
//        // First, check conditions
//        foreach ($this->getCondititions() as $condition) {
//            $this->_lastConditition = $condition;
//            /**
//             * @var $condition \HTRouter\Module\Rewrite\Condition
//             */
//
//            // Check if condition matches
//            $match = $condition->matches();
//
//            // Check if we need to AND or OR
//            if (! $match && ! $condition->hasFlag(Flag::TYPE_ORNEXT)) {
//                // Condition needs to be AND'ed, so it cannot match
//                $match = false;
//                break;
//            }
//
//            if ($match && $condition->hasFlag(Flag::TYPE_ORNEXT)) {
//                // condition needs to be OR'ed and we have already a match, no need to continue
//                $match = true;
//                break;
//            }
//        }
//
//        \HTRouter::getInstance()->getLogger()->log(\HTRouter\Logger::ERRORLEVEL_DEBUG, "Rule match of ".(string)$this." : ".($match ? "yes" : "no"));
//        return $match;
//    }
//

    /**
     * Either returns OK, DECLINED, or a HTTP status code
     *
     * @param \HTRouter\Request $request
     * @return \HTRouter\Module\Rewrite\Result
     * @throws \LogicException
     */
    function rewrite(\HTRouter\Request $request) {
        $this->_request = $request;

        // Create default return object
        $result = new Result;
        $result->vary = array();
        $result->rc = \HTRouter::STATUS_OK;

        $utils = new \HTRouter\Utils();

        $request->setUri($request->getFilename());

        // Strip per directory stuff... :|

        // Check if pattern matches
        $regex = "|".$this->_pattern."|";       // Don't separate with / since it will be used a path delimiter
        if ($this->hasFlag(Flag::TYPE_NOCASE)) {
            $regex .= "i";
        }
        $match = (preg_match($regex, $request->getUri(), $matches) >= 1);
        $this->_ruleMatches = $matches;
        if ($this->_patternNegate) {
            $match = ! $match;
        }

        // We didn't match the pattern (or negative pattern). Return unmodified url_path
        if (! $match) {
            $result->rc = \HTRouter::STATUS_NO_MATCH;
            return $result;
        }

        $conditionsPassed = $this->testConditions($request);
        if (! $conditionsPassed) {
            $result->rc = \HTRouter::STATUS_NO_MATCH;
            return $result;
        }

        if ($this->_substitutionType == self::TYPE_SUB_NONE) {
            // This is a dash, so no need to rewrite
            $result->rc = \HTRouter::STATUS_OK;
            return $result;
        }

        if ($this->_substitutionType == self::TYPE_SUB) {
            $uri = $this->expandSubstitutions($this->_substitution, $this->getRequest(), $this->_ruleMatches, $this->_condMatches);

            $src_url = parse_url($request->getUri());
            $dst_url = parse_url($uri);
            if (! isset($src_url['host'])) $src_url['host'] = "";
            if (! isset($dst_url['host'])) $dst_url['host'] = "";

            // If it's the same host or redirect flag is on, we do a redirect
            if ($dst_url['host'] != $src_url['host'] || $this->hasFlag(Flag::TYPE_REDIRECT)) {
                $url = $utils->unparse_url($dst_url);
                $request->appendOutHeaders("Location", $url);

                $result->rc = \HTRouter::STATUS_HTTP_MOVED_PERMANENTLY;
                return $result;
            }

            // Change url_path
            $request->setFilename("/".$dst_url['path']);

            // Check if we need to append our original arguments
            if (isset($dst_url['query'])) {
                parse_str($dst_url['query'], $newArgs);
            } else {
                $newArgs = array();
            }
            if ($this->hasFlag(Flag::TYPE_QSA)) {
                // We need to set new flags
                $request->setArgs(array_merge($request->getArgs(), $newArgs));
            } else {
                $request->setArgs($newArgs);
            }



            $result->rc = \HTRouter::STATUS_OK;
            return $result;
        }


        // @TODO: It should be a sub_none or sub type. Must be changed later
        // @codeCoverageIgnoreStart
        throw new \LogicException("We should not be here!");
        // @codeCoverageIgnoreEnd
    }



    /**
     * @static
     * @param $string
     * @param \HTRouter\Request $request
     * @param array $ruleMatches
     * @param array $condMatches
     * @return mixed
     * @throws \RuntimeException
     */
    static public function expandSubstitutions($string, \HTRouter\Request $request, $ruleMatches = array(), $condMatches = array())
    {
        // Do backref matching on rewriterule ($1-$9)
        preg_match_all('|\$([1-9])|', $string, $matches);
        foreach ($matches[1] as $index) {
            if (!isset($ruleMatches[$index-1])) {
                throw new \RuntimeException("Want to match index $index, but nothing found in rule to match");
            }
            $string = str_replace ("\$$index", $ruleMatches[$index-1], $string);
        }

        // Do backref matching on the last rewritecond (%1-%9)
        preg_match_all('|\%([1-9])|', $string, $matches);
        foreach ($matches[1] as $index) {
            if (!isset($condMatches[$index-1])) {
                throw new \RuntimeException("Want to match index $index, but nothing found in condition to match");
            }
            $string = str_replace ("%$index", $condMatches[$index-1], $string);
        }

        // Do variable substitution
        $string = str_replace("%{HTTP_USER_AGENT}", $request->getServerVar("HTTP_USER_AGENT"), $string);
        $string = str_replace("%{HTTP_REFERER}", $request->getServerVar("HTTP_REFERER"), $string);
        $string = str_replace("%{HTTP_COOKIE}", $request->getServerVar("HTTP_COOKIE"), $string);
        $string = str_replace("%{HTTP_FORWARDED}", $request->getServerVar("HTTP_FORWARDED"), $string);
        $string = str_replace("%{HTTP_HOST}", $request->getServerVar("HTTP_HOST"), $string);
        $string = str_replace("%{HTTP_PROXY_CONNECTION}", $request->getServerVar("HTTP_PROXY_CONNECTION"), $string);
        $string = str_replace("%{HTTP_ACCEPT}", $request->getServerVar("HTTP_ACCEPT"), $string);

        $string = str_replace("%{REMOTE_ADDR}", $request->getServerVar("REMOTE_ADDR"), $string);
        $string = str_replace("%{REMOTE_HOST}", $request->getServerVar("REMOTE_HOST"), $string);
        $string = str_replace("%{REMOTE_PORT}", $request->getServerVar("REMOTE_PORT"), $string);

        $string = str_replace("%{REMOTE_USER}", $request->getAuthUser(), $string);
        $string = str_replace("%{REMOTE_IDENT}", "", $string);                                         // We don't support identing!
        $string = str_replace("%{REQUEST_METHOD}", $request->getMethod(), $string);
        $string = str_replace("%{SCRIPT_FILENAME}", $request->getFilename(), $string);
        $string = str_replace("%{PATH_INFO}", $request->getPathInfo(), $string);
        $string = str_replace("%{QUERY_STRING}", $request->getQueryString(), $string);
        if ($request->getAuthType()) {
            $string = str_replace("%{AUTH_TYPE}", $request->getAuthType()->getName(), $string);     // Returns either Basic or Digest
        } else {
            $string = str_replace("%{AUTH_TYPE}", "", $string);
        }

        $string = str_replace("%{DOCUMENT_ROOT}", $request->getDocumentRoot(), $string);
        $string = str_replace("%{SERVER_ADMIN}", $request->getServerVar("SERVER_ADMIN"), $string);
        $string = str_replace("%{SERVER_NAME}", $request->getServerVar("SERVER_NAME"), $string);
        $string = str_replace("%{SERVER_ADDR}", $request->getServerVar("SERVER_ADDR"), $string);
        $string = str_replace("%{SERVER_PORT}", $request->getServerVar("SERVER_PORT"), $string);
        $string = str_replace("%{SERVER_PROTOCOL}", $request->getServerVar("SERVER_PROTOCOL"), $string);

        $router = \HTRouter::getInstance();
        $string = str_replace("%{SERVER_SOFTWARE}", $router->getServerSoftware(), $string);

        // Non-deterministic, but it won't change over the course of a request, even if the seconds have changed!
        $string = str_replace("%{TIME_YEAR}", date("Y"), $string);  // 2011
        $string = str_replace("%{TIME_MON}", date("m"), $string);   // 01-12
        $string = str_replace("%{TIME_DAY}", date("d"), $string);   // 01-31
        $string = str_replace("%{TIME_HOUR}", date("H"), $string);  // 00-23
        $string = str_replace("%{TIME_MIN}", date("i"), $string);   // 00-59
        $string = str_replace("%{TIME_SEC}", date("s"), $string);   // 00-59
        $string = str_replace("%{TIME_WDAY}", date("w"), $string);  // 0-6 (sun-sat)
        $string = str_replace("%{TIME}", date("YmdHis"), $string);  // %04d%02d%02d%02d%02d%02d

        $string = str_replace("%{API_VERSION}", $router->getServerApi(), $string);
        //$string = str_replace("%{THE_REQUEST}", $request->getTheRequest(), $string);  // "GET /dir HTTP/1.1"
        $string = str_replace("%{REQUEST_URI}", $request->getUri(), $string);
        $string = str_replace("%{REQUEST_FILENAME}", $request->getServerVar("SCRIPT_FILENAME"), $string);
        $string = str_replace("%{IS_SUBREQ}", $request->isSubRequest() ? "true" : "false", $string);
        $string = str_replace("%{HTTPS}", $request->isHttps() ? "on" : "off", $string);

        return $string;
    }

}
