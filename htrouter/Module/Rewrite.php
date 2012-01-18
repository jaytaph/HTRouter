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
        $router->registerHook(\HTRouter::HOOK_FIXUPS, array($this, "mimeType"), 99);
        $router->registerHook(\HTRouter::HOOK_TRANSLATE_NAME, array($this, "uriToFile"), 0);

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
     * @param \HTRequest $request
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

        $rule = new Rule($request, $args[0], $args[1], $args[2]);
        foreach ($this->getConfig()->get("TempRewriteConditions", array()) as $condition) {
            $rule->addCondition($condition);
        }
        $this->getConfig()->append("RewriteRule", $rule);

        // Clear the current rewrite conditions
        $this->getConfig()->clear("TempRewriteConditions");
    }


    /**
     * Define the hooks
     */
    function fixUp(\HTRouter\Request $request) {
        // [RewriteRules in directory context]
        if ($this->getConfig()->get("RewriteEngine") == false) {
            return \HTRouter::STATUS_DECLINED;
        }

        // @TODO: We should strip the leading directory stuff
        // @TODO: Directory must not start with /:  so=> /my/dir/index.html => dir/index.html when using /my/dir/.htacces

        $skip = 0;
        $lastMatch = true;

        // From here we can return from inside our rewriterules. Anything that needs to be set only once, must have
        // been set prior to the "thenext" label"
thenext:

        // Iterate all the rewrite rules in order!
        foreach ($this->getConfig()->get("RewriteRule") as $rule) {
            // Some flag must be parsed prior to checking stuff
            $chained = $rule->hasFlag(Flag::TYPE_CHAIN);

            /**
             * @var $rule \HTrouter\Module\Rewrite\Rule
             */
            // If the rule is chained to the last rule, and that one didn't match. Skip it
            if ($rule->hasFlag(Flag::TYPE_CHAIN) && ! $lastMatch) {
                continue;
            }

            // We still need to skip $skip amount of rules
            if ($skip > 0) {
                $skip--;
                continue;
            }

            // Skip to the next rule if this rule does not apply to sub requests        // @TODO: does this actually work?
            if ($rule->hasFlag(Flag::TYPE_NOSUBREQS) && ! $request->isMainRequest()) {
                continue;
            }

            // All done pre-parsing some flags. Now onto the actual matching

            // Check if the rule matches
            $match = $rule->matches();
            $lastMatch = $match;

            if ($match) {
                // Rewrite only when matched
                $result = $rule->rewrite($request);
                if ($result != \HTRouter::STATUS_OK || $result != \HTRouter::STATUS_DECLINED) {
                    return $result;
                }

                // Do the flags that must be done after a match
                foreach ($rule->getFlags() as $flag) {
                    switch ($flag->getType()) {
                        case Flag::TYPE_CHAIN :
                            $chained = true;
                            break;
                        case Flag::TYPE_ENV :
                            // Set environment
                            $this->getRouter()->getRequest()->appendEnvironment($flag->getKey(), $flag->getValue());
                            break;
                        case Flag::TYPE_FORBIDDEN :
                            // Forbid it!
                            return \HTRouter::STATUS_HTTP_FORBIDDEN;
                            exit;
                            break;
                        case Flag::TYPE_GONE :
                            // Just gone
                            return \HTRouter::STATUS_HTTP_GONE;
                            break;
                        case Flag::TYPE_HANDLER :
                            // @TODO: undefined!
                            throw new \LogicException("Handler setting is not supported as rewriterule flag");
                            break;
                        case Flag::TYPE_LAST :
                            // Do not evaluate any more flags or rules
                            return \HTRouter::STATUS_DECLINED;
                            break;
                        case Flag::TYPE_NEXT :
                            // Restart rules
                            goto thenext;
                            break;
                        case Flag::TYPE_MIMETYPE :
                            $request->setTempMimeType($flag->getKey());
                            break;
                        case Flag::TYPE_PROXY :
                            throw new \LogicException("Proxy is not supported as rewriterule flag");
                            break;
                        case Flag::TYPE_PASSTHROUGH :
                            // @TODO: Do passthrough
                            break;
                        case Flag::TYPE_REDIRECT :
                            $request->appendOutHeaders("Location", $url_path);
                            return \HTRouter::STATUS_HTTP_MOVED_PERMANENTLY;
                            break;
                        case Flag::TYPE_SKIP :
                            // If the rule matched, skip
                            $skip = $flag->getKey();
                            break;
                    }
                }
            } // if ($match)

            // Do stuff that needs to be done whether or not we have matched!


        }

        return \HTRouter::STATUS_DECLINED;
    }

    function mimeType(\HTRouter\Request $request) {
        // We set the mimetype last. This means that when we have N mimetype flags, only the last will be set.
        // We do this very late so our request does not get messed up with mimetypes I guess.

        // @TODO: We don't store our mimetypes inside the configuration (or do we?)
        $mimeType = $this->getConfig()->get("TempMimeType");
        if ($mimeType) {
            $request->setContentType($flag->getKey());
        }

        $handler = $this->getConfig()->get("TempHandler");
        if ($handler) {
            throw new \Exception("We cannot set handler");
        }

        return \HTRouter::STATUS_DECLINED;
    }


    function fixUp2(\HTRouter\Request $request) {
        if ($this->getConfig()->getRewriteEngine() == false) {
            return \HTRouter::STATUS_DECLINED;
        }

        // Temp save
        $oldFilename = $request->getFilename();

        if (! $request->getFilename()) {
            $request->setFilename($request->getUri());
        }

        $ruleStatus = $this->applyRewrites();
        if ($ruleStatus) {
            if ($ruleStatus == ACTION_STATUS) {
                $n = $request->getStatus();
                $request->setStatus(\HTROUTER::STATUS_HTTP_OK);
                return $n;
            }

            if (is_absolute_url($request->getFilename())) {

            } else {
                // Starts with pas
            }
        } else {
            $request->getFilename($oldFilename);
            return \HTRouter::STATUS_DECLINED;
        }
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