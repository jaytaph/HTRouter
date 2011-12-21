<?php
/**
 * Access module
 */

namespace HTRouter\Module\Authz;
use HTRouter\ModuleInterface;

class Host Extends \AuthzModule {
    // The different order constants
    const ALLOW_THEN_DENY = 1;
    const DENY_THEN_ALLOW = 2;
    const MUTUAL_FAILURE = 3;

    public function init(\HTRouter $router)
    {
        parent::init($router);

        // Register directive
        $router->registerDirective($this, "allow");
        $router->registerDirective($this, "deny");
        $router->registerDirective($this, "order");

        // Register hook
        $router->registerHook(\HTRouter::HOOK_CHECK_ACCESS, array($this, "checkAccess"));

        // Default value
        $router->getRequest()->setAccessOrder(self::DENY_THEN_ALLOW);

        // @TODO: Remove debug things
        $router->getRequest()->appendEnvironment("test", 1);
    }


    public function checkUserAccess(\HTRequest $request)
    {
        // Not needed, we are hooking in check_access
    }


    public function allowDirective(\HTRequest $request, $line) {
        if (! preg_match("/^from (.+)$/i", $line, $match)) {
            throw new \UnexpectedValueException("allow must be followed by a 'from'");
        }

        // Convert each item on the line to our custom "entry" object
        foreach ($this->_convertToEntry($match[1]) as $item) {
            $request->appendAccessAllow($item);
        }
    }

    public function denyDirective(\HTRequest $request, $line) {
        if (! preg_match("/^from (.+)$/i", $line, $match)) {
            throw new \UnexpectedValueException("deny must be followed by a 'from'");
        }

        // Convert each item on the line to our custom "entry" object
        foreach ($this->_convertToEntry($match[1]) as $item) {
            $request->appendAccessDeny($item);
        }

    }

    public function orderDirective(\HTRequest $request, $line) {
        // Funny.. Apache does a strcmp on "allow,deny", so you can't have "allow, deny" spaces in between.
        // So we shouldn't allow it either.

        if ($line == "allow,deny") {
            $request->setAccessOrder(self::ALLOW_THEN_DENY);
        } elseif ($line == "deny,allow") {
            $request->setAccessOrder(self::DENY_THEN_ALLOW);
        } elseif ($line == "mutual-failure") {
            $request->setAccessOrder(self::MUTUAL_FAILURE);
        } else {
            throw new \DomainException("Unknown order: ".$line);
        }
    }


    /**
     * These functions should return true|false or something to make sure we can continue with our stuff?
     *
     * @param \HTRequest $request
     * @return bool
     * @throws \LogicException
     */
    public function checkAccess(\HTRequest $request) {

        // The way we parse things depends on the "order"
        switch ($request->getAccessOrder()) {
            case self::ALLOW_THEN_DENY :
                $result = false;
                if ($this->_findAllowDeny($request->getAccessAllow())) {
                    $result = true;
                }
                if ($this->_findAllowDeny($request->getAccessDeny())) {
                    $result = false;
                }
                break;
            case self::DENY_THEN_ALLOW :
                $result = true;
                if ($this->_findAllowDeny($request->getAccessDeny())) {
                    $result = false;
                }
                if ($this->_findAllowDeny($request->getAccessAllow())) {
                    $result = true;
                }
                break;
            case self::MUTUAL_FAILURE :
                if ($this->_findAllowDeny($request->getAccessAllow()) and
                    !$this->_findAllowDeny($request->getAccessDeny())) {
                    $result = true;
                } else {
                    $result = false;
                }
                break;
            default:
                throw new \LogicException("Unknown order");
                break;
        }

        // Not ok. Now we need to check if "satisfy any" already got a satisfaction
        if ($result == false) {

            // @TODO: Check satisfy
            $this->_router->createForbiddenResponse();
            exit;
        }

        // Everything is ok
        return true;
    }

    protected function _findAllowDeny(array $items) {
        $utils = new \HTUtils();

        // Iterate all "ALLOW" or "DENY" items. We just return if at least one of them matches
        foreach ($items as $entry) {
            switch ($entry->type) {
                case "env" :
                    $env = $this->_router->getRequest()->getEnvironment();
                    if (isset($env[$entry->env])) return true;
                    break;
                case "nenv" :
                    $env = $this->_router->getRequest()->getEnvironment();
                    if (! isset ($env[$entry->env])) return true;
                    break;
                case "all" :
                    return true;
                    break;
                case "ip" :
                    if ($utils->checkMatchingIP($entry->ip, $this->_request->getIp())) return true;
                    break;
                case "host" :
                    if ($utils->checkMatchingHost($entry->host, $this->_request->getHost())) return true;
                    break;
                default:
                    throw new \LogicException("Unknown entry type: ".$entry->type);
                    break;
            }
        }
        return false;
    }

    /**
     * Convert a line to an array of simple entry objects
     *
     * @param $line
     */
    protected function _convertToEntry($line) {
        $entries = array();

        foreach (explode(" ", $line) as $item) {
            $entry = new \StdClass();

            if ($item == "all") {
                $entry->type = "all";
                $entries[] = $entry;
                continue;
            }

            // Must be parsed BEFORE env= is parsed!
            if (substr($item, 0, 5) === "env=!") {
                $entry->type = "nenv";
                $entry->env = substr($item, 5);
                $entries[] = $entry;
                continue;
            }

            if (substr($item, 0, 4) === "env=") {
                $entry->type = "env";
                $entry->env = substr($item, 4);
                $entries[] = $entry;
                continue;
            }

            if (strchr($item, "/")) {
                // IP with subnet mask or cidr
                $entries[] = $entry;
                continue;
            }
            if (preg_match("/[\d\.]+/", $line)) {
                // Looks like it's an IP or partial IP
                $entry->type = "ip";
                $entry->ip = $line;
                $entries[] = $entry;
                continue;
            }

            // Nothing found, treat as (partial) hostname
            $entry->type = "host";
            $entry->host = $line;
            $entries[] = $entry;
        }

        return $entries;
    }


    public function getName() {
        return "authz_host";
    }

}