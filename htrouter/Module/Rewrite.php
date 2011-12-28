<?php
/**
 * Mod_rewrite. This is probably the most important module (and the whole point of the router to begin with)
 */

namespace HTRouter\Module;
use HTRouter\Module;
use HTRouter\Module\Rewrite\Condition;
use HTRouter\Module\Rewrite\Rule;
use HTRouter\Module\Rewrite\Flag;


class Rewrite extends Module {

    public function init(\HTRouter $router)
    {
        // Register directives
        parent::init($router);
        $router->registerDirective($this, "RewriteBase");
        $router->registerDirective($this, "RewriteCond");
        $router->registerDirective($this, "RewriteEngine");
        $router->registerDirective($this, "RewriteOptions");
        $router->registerDirective($this, "RewriteRule");

        // Only register the hooks that are of value to us
        $router->registerHook(\HTRouter::HOOK_FIXUPS, array($this, "fixUp"), 0);
        $router->registerHook(\HTRouter::HOOK_FIXUPS, array($this, "mimeType"), 99);
        $router->registerHook(\HTRouter::HOOK_TRANSLATE_NAME, array($this, "uriToFile"), 0);

        // Default values
        $router->getRequest()->config->setRewriteEngine(false);
    }

    public function RewriteEngineDirective(\HTRouter\Request $request, $line) {
        $utils = new \HTRouter\Utils();
        $value = $utils->fetchDirectiveFlags($line, array("on" => true, "off" => false));
        $request->config->setRewriteEngine($value);
    }

    public function RewriteBaseDirective(\HTRouter\Request $request, $line) {
        $request->config->setRewriteBase($line);
    }

    /**
     * Rewrite conditions are saves to a "temporary" storage. A rewriteRule directive will pick them up. It's
     * imperative that the directives are read top-down since there is no other way of ordering conditions.
     *
     * @param \HTRequest $request
     * @param $line
     */
    public function RewriteCondDirective(\HTRouter\Request $request, $line) {
        $args = explode(" ", $line, 3);
        if (count ($args) <= 2) {
            // Add optional flags
            $args[] = "";
        }

        $condition = new Condition($args[0], $args[1], $args[2]);
        $request->config->appendTempRewriteConditions($condition);
    }

    public function RewriteOptionsDirective(\HTRouter\Request $request, $line) {
        if ($line != "inherit") {
            throw new \UnexpectedValueException("RewriteOptions must be 'inherit'");
        }
        $request->config->setRewriteOptions("inherit");
    }

    public function RewriteRuleDirective(\HTRouter\Request $request, $line) {
        $args = explode(" ", $line, 3);
        if (count ($args) <= 2) {
            // Add optional flags
            $args[] = "";
        }

        $rule = new Rule($request, $args[0], $args[1], $args[2]);
        foreach ($request->config->getTempRewriteConditions(array()) as $condition) {
            $rule->addCondition($condition);
        }
        $request->config->appendRewriteRule($rule);

        // Clear the current rewrite conditions
        $request->config->unsetTempRewriteConditions();
    }


    /**
     * Define the hooks
     */
    function fixUp(\HTRouter\Request $request) {
        // [RewriteRules in directory context]
        if ($request->config->getRewriteEngine() == false) {
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
        foreach ($request->config->getRewriteRule() as $rule) {
            // Some flag must be parsed prior to checking stuff
            $chained = $rule->hasFlag(Flag::TYPE_CHAIN);

            // If the rule is chained to the last rule, and that one didn't match. Skip it
            if ($chained && ! $lastMatch) {
                continue;
            }

            // We need to skip $skip amount of rules
            if ($skip > 0) {
                $skip--;
                continue;
            }

            // Skip to the next rule if this rule does not apply to sub requests
            if ($rule->hasFlag(Flag::TYPE_NOSUBREQS) && $request->isSubRequest()) {
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
                            $this->getRouter()->createForbiddenResponse();
                            exit;
                            break;
                        case Flag::TYPE_GONE :
                            // Just gone
                            $this->getRouter()->createRedirect(410, "Gone");
                            break;
                        case Flag::TYPE_HANDLER :
                            $request->setTempHandler($flag->getKey());
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
                            throw new \Exception("PRoxy is not supported as rewriterule flag");
                            break;
                        case Flag::TYPE_PASSTHROUGH :
                            // @TODO: Do passthrough
                            break;
                        case Flag::TYPE_REDIRECT :
                            $code = $flag->getKey();
                            if (empty($code)) $code = 302;
                            $this->getRouter()->createRedirect($code, $utils->getStatusLine($code), $url_path);
                            break;
                        case Flag::TYPE_SKIP :
                            // If the rule matched, skip
                            $skip = $flag->getKey();
                            break;
                    }
                }
            } // if ($match)

            // Do stuff that needs to be done wether or not we have matched!


        }

        return \HTRouter::STATUS_DECLINED;
    }

    function mimeType(\HTRouter\Request $request) {
        // We set the mimetype last. This means that when we have N mimetype flags, only the last will be set.
        // We do this very late so our request does not get messed up with mimetypes I guess.

        $mimeType = $request->config->getTempMimeType();
        if ($mimeType) {
            $request->setContentType($flag->getKey());
        }

        $handler = $request->config->getTempHandler();
        if ($handler) {
            throw new \Exception("We cannot set handler");
        }

        return \HTRouter::STATUS_DECLINED;
    }

    function uriToFile(\HTRouter\Request $request) {
        // [RewriteRules in server context]
        // @TODO: I don't think this one is needed, since we only do .htaccess context
        return \HTRouter::STATUS_DECLINED;
    }


    /**
     * Internal functionality
     */


    /**
     * Return module aliases
     *
     * @return string
     */
    public function getAliases()
    {
        return array("mod_rewrite.c", "module_rewrite", "rewriteModule");
    }

}