<?php
/**
 * Mod_rewrite. This is probably the most important module (and the whole point of the router to begin with). Note
 * however that this module is not writing in same the way the actual mod_rewrite was written. Since performance isn't
 * an issue, but severely complicates the whole setup, we keep all the rewrites internally inside this function. There
 * is no internal-redirector or subrequests that are made by this function. Input: url  Output: completely rewritten url
 */

namespace HTRouter\Module;
use HTRouter\Module;
use HTRouter\Module\Rewrite\Condition;
use HTRouter\Module\Rewrite\Rule;
use HTRouter\Module\Rewrite\Flag;


class Rewrite extends Module {
    const ACTION_NORMAL    = 1;      // Normal return
    const ACTION_NOESCAPE  = 2;      // No escaping of the URL
    const ACTION_STATUS    = 3;      // The request->getStatus() returns an actual status code


    public function init(\HTRouter $router, \HTRouter\HTDIContainer $container)
    {
        parent::init($router, $container);

        // Register directives
        $router->registerDirective($this, "RewriteBase");
        $router->registerDirective($this, "RewriteCond");
        $router->registerDirective($this, "RewriteEngine");
        $router->registerDirective($this, "RewriteOptions");
        $router->registerDirective($this, "RewriteRule");

        // Only register the hooks that are of value to us
        $router->registerHook(\HTRouter::HOOK_FIXUPS, array($this, "fixUp"), 0);

        // Set default values
        $this->getConfig()->set("RewriteEngine", false);
    }

    public function RewriteEngineDirective(\HTRouter\Request $request, $line) {
        $utils = new \HTRouter\Utils();
        $value = $utils->fetchDirectiveFlags($line, array("on" => true, "off" => false));
        $this->getConfig()->set("RewriteEngine", $value);
    }

    public function RewriteBaseDirective(\HTRouter\Request $request, $line) {
        $this->getConfig()->set("RewriteBase", $line);
    }

    /**
     * Rewrite conditions are saves to a "temporary" storage. A rewriteRule directive will pick them up. It's
     * imperative that the directives are read top-down since there is no other way of ordering conditions.
     *
     * @param \HTRequest|\HTRouter\Request $request
     * @param $line
     */
    public function RewriteCondDirective(\HTRouter\Request $request, $line) {
        $args = preg_split("/\s+/", $line, 3);
        if (count ($args) <= 2) {
            // Add optional flags
            $args[] = "";
        }

        $condition = new Condition($args[0], $args[1], $args[2]);
        $this->getConfig()->append("TempRewriteConditions", $condition);
    }

    public function RewriteOptionsDirective(\HTRouter\Request $request, $line) {
        // @TODO: This must be fixed. At this point, everything is always inherited
        if ($line != "inherit") {
            throw new \InvalidArgumentException("RewriteOptions must be 'inherit'");
        }
        $this->getConfig()->set("RewriteOptions", "inherit");
    }

    public function RewriteRuleDirective(\HTRouter\Request $request, $line) {
        $args = explode(" ", $line, 3);
        if (count ($args) <= 2) {
            // Add optional flags
            $args[] = "";
        }

        $rule = new Rule($args[0], $args[1], $args[2]);
        foreach ($this->getConfig()->get("TempRewriteConditions", array()) as $condition) {
            $rule->addCondition($condition);
        }
        $this->getConfig()->append("RewriteRule", $rule);

        // Clear the current rewrite conditions
        $this->getConfig()->clear("TempRewriteConditions");
    }

    public function mergeConfigs(\HTRouter\VarContainer $base, \HTRouter\VarContainer $add)
    {
        if ($add->get("RewriteEngine", false)) {
            $base->set("RewriteEngine", $add->get("RewriteEngine"));
        }
        if ($add->get("RewriteOptions", false)) {
            $base->set("RewriteOptions", $add->get("RewriteOptions"));
        }
        if ($add->get("RewriteBase", false)) {
            $base->set("RewriteBase", $add->get("RewriteBase"));
        }

        if ($base->get("RewriteOptions", "") == "inherit") {
            $merged = array_merge($add->get("RewriteRule", array()), $base->get("RewriteRule", array()));
            if (count($merged)) $base->set("RewriteRule", $merged);
        } else {
            if ($add->get("RewriteRule", false)) {
                $base->set("RewriteRule", $add->get("RewriteRule"));
            }
        }
    }


    protected function _applyRewrites() {
        $request = $this->getRequest();

        $changed = self::ACTION_NORMAL;
nextloop:
        $skip = 0;
        $matched = true;
        $rulelist = $this->getConfig()->get("RewriteRule");
        foreach ($rulelist as $rule) {
            /**
             * @var $rule \HTRouter\Module\Rewrite\Rule
             */

            // If last rule didn't match and the current rule is chained, skip it
            if (! $matched && $rule->hasFlag(Flag::TYPE_CHAIN)) {
                continue;
            }

            // If we need rules to skip, do this here
            if ($skip) {
                $skip--;
                continue;
            }

            // If we are a subrequest and we don't need subrequests we have a redirect, do not match this rule
            if ($request->isSubRequest() && ($rule->hasFlag(Flag::TYPE_NOSUBREQS) || $rule->hasFlag(Flag::TYPE_REDIRECT))) {
                continue;
            }

            // Check if rule matches
            $result = $rule->rewrite($this->getRequest());
            if ($result->rc == \HTRouter::STATUS_OK) {
                // Rule matched
                $matched = true;

                if ($rule->hasFlag(Flag::TYPE_COOKIE)) {
                    // Set cookie
                }
                if ($rule->hasFlag(Flag::TYPE_ENV)) {
                    // Set environment
                }


                // Add vary headers
                $varyHeaders = $result->vary;
                if (count($varyHeaders)) {
                    $request->appendOutHeaders("Vary", $varyHeaders);
                }

                // Redirect? We can return immediately
                if ($rule->hasFlag(Flag::TYPE_REDIRECT)) {
                    return self::ACTION_STATUS;
                }

                // Do we need to escape?
                //if ($result->rc != 2) {
                    $changed = $rule->hasFlag(Flag::TYPE_NOESCAPE) ? self::ACTION_NOESCAPE : self::ACTION_NORMAL;
                //}

                // Passthrough found, we must use the passthrough handler later on
                if ($rule->hasFlag(Flag::TYPE_PASSTHROUGH)) {
                    $request->setFilename("passthrough:".$request->getFilename());
                    $changed = self::ACTION_NORMAL;
                }

                // Proxy or last, so no more processing
                if ($rule->hasFlag(Flag::TYPE_PROXY) || $rule->hasFlag(Flag::TYPE_LAST)) {
                    break;
                }

                // Rerun the rules.
                if ($rule->hasFlag(Flag::TYPE_NEXT)) {
                    goto nextloop;
                }

                // Do we need to skip? If so, how much
                if ($rule->hasflag(Flag::TYPE_SKIP)) {
                    $flag = $rule->getFlag(Flag::TYPE_SKIP);
                    /**
                     * @var $flag \HTRouter\Module\Rewrite\Flag
                     */
                    $skip = $flag->getKey();    // We skip rules until $skip is zero again
                }
             } else {
                // We did not match
                $matched = false;
            }
        }

        return $changed;
    }

    function fixUp(\HTRouter\Request $request) {
        if ($this->getConfig()->get("RewriteEngine") == false) {
            return \HTRouter::STATUS_DECLINED;
        }

        // Temp save
        $oldFilename = $request->getFilename();

        if (! $request->getFilename()) {
            $request->setFilename($request->getUri());
        }

        $ruleStatus = $this->_applyRewrites();
        if ($ruleStatus) {
            if ($ruleStatus == self::ACTION_STATUS) {
                $n = $request->getStatus();
                $request->setStatus(\HTROUTER::STATUS_HTTP_OK);
                return $n;
            }

            if (($skip = $this->_is_absolute_url($request->getFilename())) > 0) {
                if ($ruleStatus == self::ACTION_NOESCAPE) {
                    $request->setFilename(urlencode($request->getFilename(), $skip));
                }

                // Add query string if needed
                if ($request->getArgs()) {
                    if ($ruleStatus == self::ACTION_NOESCAPE) {
                        $request->setFilename( $request->getFilename() . "?" . $request->getQueryString());
                    } else {
                        $request->setFilename( $request->getFilename() . "?" . urlencode($request->getQueryString()));
                    }
                }

                // Is this a redirect?
                if ($request->getStatus() >= 300 && $request->getStatus() < 400) {
                    $n = $request->getStatus();
                    $request->setStatus(\HTRouter::STATUS_HTTP_OK);
                } else {
                    // No redirect, but we need to redir anyway..
                    $n = \HTRouter::STATUS_HTTP_MOVED_TEMPORARILY;
                }

                // The filename is the URI to redirect.. strange, I know...
                $request->appendOutHeaders("Location", $request->getFilename());
                return $n;

            } elseif (substr($request->getFilename(), 0, 12) == "passthrough:") {
                // Starts with passthrough? Let's pass
                $request->setUri(substr($request->getFilename(), 13));
                return \HTRouter::STATUS_DECLINED;
            } else {
                // Local path

                if ($oldFilename == $request->getFilename()) {
                    // Rewrite to the same name. Prevent deadlocks
                    return \HTRouter::STATUS_HTTP_OK;
                }
            }
        } else {
            $request->getFilename($oldFilename);
            return \HTRouter::STATUS_DECLINED;
        }

        return \HTRouter::STATUS_DECLINED;
    }



    protected function _is_absolute_url($url) {
        $tmp = parse_url($url);
        return (isset($tmp['schema'])) ? strlen($tmp['schema']) : 0;
    }


    protected function _prepareContainerForSubRequest($url) {
        $subrequest = clone ($this->_container->getRequest());
        $subrequest->setMainRequest(false);
        $subrequest->setUri($url);
        $subrequest->setFilename(null);
        $subrequest->setRecursionLevel($this->_container->getRequest()->getRecursionLevel() + 1);

        $subContainer = clone ($this->_container);
        //$subContainer->name = $this->_container->name . " (SubRequest)";
        //$subContainer->setConfig($this->_container->getRouter()->getDefaultConfig());
        $subContainer->setRequest($subrequest);

        return $subContainer;
    }

    /**
     * Return module aliases
     *
     * @return string
     */
    public function getAliases()
    {
        return array("mod_rewrite", "rewrite", "mod_rewrite.c", "module_rewrite", "rewriteModule");
    }

}