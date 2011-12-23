<?php

// Create a new htrouter phar file
@unlink('htrouter.phar');
$phar = new Phar('htrouter.phar', 0, 'htrouter.phar');


$basePath = realpath(dirname(__FILE__)."/..");

// Create iterator
$it = new RecursiveDirectoryIterator($basePath);
$it = new UnwantedRecursiveFilterIterator($it, array(".idea", ".git"));
$it = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::SELF_FIRST);
$phar->buildFromIterator($it, realpath($basePath."/.."));

// Add stub
$phar->setStub($phar->createDefaultStub('router/public/router.php', 'router/public/router.php'));


/**
 * Simple filter that will skip a serie of directories
 */
class UnwantedRecursiveFilterIterator extends RecursiveFilterIterator {
    protected $_excludedPatterns = array();

    public function __construct(RecursiveIterator $it, array $excludedPatterns)
    {
        $this->_excludedPatterns = $excludedPatterns;
        parent::__construct($it);
    }

    public function accept()
    {
        foreach ($this->_excludedPatterns as $pattern) {
            if (fnmatch($pattern, $this->current()->getBasename())) {
                return false;
            }
        }
        return true;
    }

    function getChildren()
    {
        $it = new RecursiveDirectoryIterator($this->current());
        return new UnwantedRecursiveFilterIterator($it, $this->_excludedPatterns);
    }
}
