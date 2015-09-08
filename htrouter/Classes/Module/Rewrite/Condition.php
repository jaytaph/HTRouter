<?php

namespace HTRouter\Module\Rewrite;

class Condition {
//    // TestString types
//    const TYPE_UNKNOWN           = 0;
//    const TYPE_RULE_BACKREF      = 1;
//    const TYPE_COND_BACKREF      = 2;
//    const TYPE_SERVER            = 3;
//    const TYPE_SPECIAL           = 4;

    // Conditional Pattern types
    const COND_UNKNOWN           =  0;
    const COND_REGEX             =  1;
    const COND_LEXICAL_PRE       =  2;
    const COND_LEXICAL_POST      =  3;
    const COND_LEXICAL_EQ        =  4;
    const COND_TEST_DIR          =  5;
    const COND_TEST_FILE         =  6;
    const COND_TEST_SIZE         =  7;
    const COND_TEST_SYMLINK      =  8;
    const COND_TEST_EXECUTE      =  9;
    const COND_TEST_FILE_SUBREQ  = 10;
    const COND_TEST_URL_SUBREQ   = 11;


    protected $_specialTypes = array('IS_SUBREQ', 'API_VERSION', 'THE_REQUEST', 'REQUEST_URI', 'REQUEST_FILENAME', 'HTTPS');
    protected $_specialServer = array('HTTP_USER_AGENT','HTTP_REFERER','HTTP_COOKIE','HTTP_FORWARDED','HTTP_HOST','HTTP_PROXY_CONNECTION','HTTP_ACCEPT',
                                      'REMOTE_ADDR','REMOTE_HOST','REMOTE_PORT','REMOTE_USER','REMOTE_IDENT','REQUEST_METHOD','SCRIPT_FILENAME','PATH_INFO','QUERY_STRING','AUTH_TYPE',
                                      'DOCUMENT_ROOT','SERVER_ADMIN','SERVER_NAME','SERVER_ADDR','SERVER_PORT','SERVER_PROTOCOL','SERVER_SOFTWARE',
                                      'TIME_YEAR','TIME_MON','TIME_DAY','TIME_HOUR','TIME_MIN','TIME_SEC','TIME_WDAY','TIME');

    protected $_testString;

    protected $_condPattern;
    protected $_condPatternType;
    protected $_condPatternNegate;

    protected $_matches = array();      // The last matches found when parsing the condition

    /**
     * @var null|\HTRouter\Module\Rewrite\Rule
     */
    protected $_rule = null;

    protected $_match = null;  // True when the condition has matched before already.

    function __construct($testString, $condPattern, $flags = '') {
        // Set default values
        $this->_testString = $testString;

        $this->_condPattern = $condPattern;
        $this->_condPatternType = self::COND_UNKNOWN;
        $this->_condPatternNegate = false;
        $this->_condPatternNocase = false;
        $this->_condPatternOr = false;

        $this->_flags = array();

        // Parse string and condition (throws error on fault)
        $this->_parseCondPattern($condPattern);
        $this->_parseFlags($flags);
    }

    function __toString() {
        $ret = $this->_testString." ".($this->_condPatternNegate?"!":"").$this->_condPattern;
        if (count($this->_flags)) $ret .= " [".join(", ", $this->_flags)."]";
        return $ret;
    }

    protected function _parseCondPattern($condPattern) {
        if (empty($condPattern)) {
            throw new \InvalidArgumentException("CondPattern must not be empty!");
        }

        // Check if its a negative condition
        if ($condPattern[0] == "!") {
            // CondPattern argument must be modified as well!
            $condPattern = $this->_condPattern = substr($condPattern, 1);
            $this->_condPatternNegate = true;
        }


        // It's a regex unless we decide otherwise
        $this->_condPatternType = self::COND_REGEX;


        if ($condPattern[0] == "<" && strlen($condPattern) > 1) {
            $this->_condPattern = substr($condPattern, 1);
            $this->_condPatternType = self::COND_LEXICAL_PRE;
        } elseif ($condPattern[0] == ">" and strlen($condPattern) > 1) {
            $this->_condPattern = substr($condPattern, 1);
            $this->_condPatternType = self::COND_LEXICAL_POST;
        } elseif ($condPattern[0] == "=" and strlen($condPattern) > 1) {
            $this->_condPattern = substr($condPattern, 1);
            $this->_condPatternType = self::COND_LEXICAL_EQ;
        } elseif ($condPattern[0] == "-" and strlen($condPattern) == 2) {
            switch ($condPattern) {
                case "-d" :
                    $this->_condPatternType = self::COND_TEST_DIR;
                    break;
                case "-f" :
                    $this->_condPatternType = self::COND_TEST_FILE;
                    break;
                case "-s" :
                    $this->_condPatternType = self::COND_TEST_SIZE;
                    break;
                case "-l" :
                    $this->_condPatternType = self::COND_TEST_SYMLINK;
                    break;
                case "-x" :
                    $this->_condPatternType = self::COND_TEST_EXECUTE;
                    break;
                case "-F" :
                    $this->_condPatternType = self::COND_TEST_FILE_SUBREQ;
                    break;
                case "-U" :
                    $this->_condPatternType = self::COND_TEST_URL_SUBREQ;
                    break;
            }
        }
    }


    protected function _parseFlags($flags) {
        if (empty($flags)) return;

        // Check for brackets
        if ($flags[0] != '[' && $flags[strlen($flags)-1] != ']') {
            throw new \InvalidArgumentException("Flags must be bracketed");
        }

        // Remove brackets
        $flags = substr($flags, 1, -1);

        foreach (explode(",",$flags) as $flag) {
            $flag = trim($flag);

            switch (strtolower($flag)) {
                case "nocase" :
                case "nc" :
                    $this->_flags[] = new Flag(Flag::TYPE_NOCASE, null, null);
                    break;
                case "ornext" :
                case "or" :
                    $this->_flags[] = new Flag(Flag::TYPE_ORNEXT, null, null);
                    break;
                case "novary" :
                case "nv" :
                    $this->_flags[] = new Flag(Flag::TYPE_NOVARY, null, null);
                    break;
                default :
                    throw new \InvalidArgumentException("Unknown condition flag: $flag");
                    break;
            }
        }
    }


    function hasFlag($type) {
        return ($this->getFlag($type) != null);
    }

    /**
     * @return array \HTRouter\Module\Rewrite\Flag
     */
    function getFlags() {
        return $this->_flags;
    }

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
     * Returns true if the condition matches, false otherwise. We don't mind non-deterministic conditions like TIME_*
     *
     * @return bool
     */
    public function matches($request) {
        if ($this->_match == null) {
            // Cache it
            $this->_match = $this->_checkMatch($request);
        }

        return $this->_match;
    }

    function getLastMatches() {
        return $this->_matches;
    }

    /**
     * Actual workload of condition matching
     * @return bool
     */
    protected function _checkMatch($request) {
        $expanded = Rule::expandSubstitutions($this->_testString, $request);

        $match = false;

        // Check expanded string against conditional Pattern
        switch ($this->_condPatternType) {
            case self::COND_REGEX :
                $regex = '|'.$this->_condPattern.'|';  // Don't separate with / since it will be used a path delimiter

                // Case independent if needed
                if ($this->hasFlag(Flag::TYPE_NOCASE)) {
                    $regex .= "i";
                }

                // Check regex
                $match = (preg_match($regex, $expanded, $matches) >= 1);

                // Store matches in case we need to do back references %N inside rules
                $this->_matches = $matches;
                break;

            case self::COND_LEXICAL_PRE :
                // PRE and POST lexical match does not follow the nocase fields!
                $sub = substr($expanded, 0, strlen($this->_condPattern));
                $match = (strcmp($sub, $this->_condPattern) == 0);
                break;

            case self::COND_LEXICAL_POST :
                // PRE and POST lexical match does not follow the nocase fields!
                $sub = substr($expanded, 0-strlen($this->_condPattern));
                $match = (strcmp($sub, $this->_condPattern) == 0);
                break;

            case self::COND_LEXICAL_EQ :
                if ($this->hasFlag(Flag::TYPE_NOCASE)) {
                    $match = (strcasecmp($this->_condPattern, $expanded) == 0);
                } else {
                    $match = (strcmp($this->_condPattern, $expanded) == 0);
                }
                break;

            case self::COND_TEST_DIR :
                $match = is_dir($expanded);
                break;
            case self::COND_TEST_FILE :
                $match = is_file($expanded);
                break;
            case self::COND_TEST_SIZE :
                $match = (is_file($expanded) && filesize($expanded) > 0);
                break;
            case self::COND_TEST_SYMLINK :
                $match = is_link($expanded);
                break;
            case self::COND_TEST_EXECUTE :
                $match = is_executable($expanded);
                break;
            case self::COND_TEST_FILE_SUBREQ :
                // @TODO: What to do?
                break;
            case self::COND_TEST_URL_SUBREQ :
                // @TODO: What to do?
                break;
        }


        // If the match must be negated, make it so
        if ($this->_condPatternNegate) {
            $match = ! $match;
        }

        \HTRouter::getInstance()->getLogger()->log(\HTRouter\Logger::ERRORLEVEL_DEBUG, "Conditional match of ".(string)$this." : ".($match ? "yes" : "no"));
        return $match;
    }

}