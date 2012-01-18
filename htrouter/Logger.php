<?php

namespace HTRouter;

/**
 * Simple logger class
 */
class Logger {

    const ERRORLEVEL_NONE    = 0;   // No logging
    const ERRORLEVEL_DEBUG   = 1;   // Debug info
    const ERRORLEVEL_NOTICE  = 2;   // Notices
    const ERRORLEVEL_WARNING = 3;   // Warnings, but not severe
    const ERRORLEVEL_ERROR   = 4;   // Errors, halting

    protected $_errorMappings = array("debug" => self::ERRORLEVEL_DEBUG,
                                      "notice" => self::ERRORLEVEL_NOTICE,
                                      "warning" => self::ERRORLEVEL_WARNING,
                                      "error" => self::ERRORLEVEL_ERROR);

    protected $_isConfigured = false;

    /**
     * @var string
     */
    protected $_logLevel = null;

    /**
     * @var resource
     */
    protected $_logFileHandle = null;

    /**
     * @var resource
     */
    protected $_logSyslogHandle = false;

    /**
     * @var array All errors that resulted in this request
     */
    protected $_logs = array();


    function __construct(\HTRouter\HTDIContainer $container) {
        // Initialize logger
        $config = $container->getRouterConfig("logger");
        $this->_init($config);
    }


    /**
     * Set errors that might have occurred.
     *
     * @param int $level The log level
     * @param string $error The error message
     */
    function log($level, $error) {
        // Always store the error
        $this->_logs[] = $level.": ".$error;

        // @TODO: Remove me
        print "&bull;<b>Logline: <font color=#4169e1>. $error</font></b><br>";

        // No need to lpg this
        if ($level < $this->_logLevel) return;

        // Log to syslog
        if ($this->_logSyslogHandle || ! $this->_logFileHandle) {
            $this->_logToSyslog($level, $error);
        } else {
            $this->_logToFile($level, $error);
        }


    }

    /**
     * Returns all errors in this request
     *
     * @return array
     */
    function getLogs() {
        return $this->_logs;
    }


    protected function _init($config) {
        // Default error level
        $this->_logLevel == self::ERRORLEVEL_NONE;

        // Fetch correct loglevel
        if (isset ($config['loglevel'])) {
            $tmp = strtolower($config['loglevel']);
            if (isset ($this->_errorMappings[$tmp])) {
                $this->_logLevel = $this->_errorMappings[$tmp];
            }
        }

        // Check logfile
        if (isset ($config['logfile'])) {
            $tmp = $config['logfile'];
            if ($tmp == "-") {
                // Log to syslog
                // openlog("htrouter", LOG_PID | LOG_PERROR, LOG_USER);
                $this->_logSyslogHandle = true;
            } else {
                // Otherwise, open file
                $this->_logFileHandle = fopen($tmp, "a");
            }
        }
    }


    protected function _logToSyslog($level, $error) {
        switch ($level) {
            default :
            case self::ERRORLEVEL_NOTICE :
                $sysLevel = LOG_NOTICE;
                break;
            case self::ERRORLEVEL_DEBUG :
                $sysLevel = LOG_DEBUG;
                break;
            case self::ERRORLEVEL_WARNING :
                $sysLevel = LOG_WARNING;
                break;
            case self::ERRORLEVEL_ERROR :
                $sysLevel = LOG_ERR;
                break;
        }
        syslog($sysLevel, $error);
    }

    protected function _logToFile($level, $error) {
        // Do file logging
       switch ($level) {
           case self::ERRORLEVEL_DEBUG :
               $levelStr = "Debug";
               break;
           default:
           case self::ERRORLEVEL_NOTICE :
               $levelStr = "Notice";
               break;
           case self::ERRORLEVEL_WARNING :
               $levelStr = "Warning";
               break;
           case self::ERRORLEVEL_ERROR :
               $levelStr = "Error";
               break;
       }
       $timestamp = date("Y/m/d H:i:s");
       fwrite($this->_logFileHandle, "[".$timestamp."] ".$levelStr." ".$error."\n");
    }

}
