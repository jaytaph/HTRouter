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

        // Lots of hooks
        $router->registerHook(\HTRouter::HOOK_HANDLER, array($this, "handlerRedirect"));
        $router->registerHook(\HTRouter::HOOK_PRE_CONFIG, array($this, "preConfig"));
        $router->registerHook(\HTRouter::HOOK_POST_CONFIG, array($this, "postConfig"));
        $router->registerHook(\HTRouter::HOOK_CHILD_INIT, array($this, "childInit"));

        $router->registerHook(\HTRouter::HOOK_FIXUPS, array($this, "fixUp"), 0);
        $router->registerHook(\HTRouter::HOOK_FIXUPS, array($this, "mimeType"), 99);
        $router->registerHook(\HTRouter::HOOK_TRANSLATE_NAME, array($this, "uriToFile"), 0);

        // Default values
        $router->getRequest()->setRewriteEngine(false);
    }

    public function RewriteEngineDirective(\HTRouter\Request $request, $line) {
        $utils = new \HTRouter\Utils();
        $value = $utils->fetchDirectiveFlags($line, array("on" => true, "off" => false));
        $request->setRewriteEngine($value);
    }

    public function RewriteBaseDirective(\HTRouter\Request $request, $line) {
        $request->setRewriteBase($line);
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
        $request->appendTempRewriteConditions($condition);
    }

    public function RewriteOptionsDirective(\HTRouter\Request $request, $line) {
        if ($line != "inherit") {
            throw new \UnexpectedValueException("RewriteOptions must be 'inherit'");
        }
        $request->setRewriteOptions("inherit");
    }

    public function RewriteRuleDirective(\HTRouter\Request $request, $line) {
        $args = explode(" ", $line, 3);
        if (count ($args) <= 2) {
            // Add optional flags
            $args[] = "";
        }

        $rule = new Rule($request, $args[0], $args[1], $args[2]);
        foreach ($request->getTempRewriteConditions(array()) as $condition) {
            $rule->addCondition($condition);
        }
        $request->appendRewriteRule($rule);

        // Clear the current rewrite conditions
        $request->unsetTempRewriteConditions();
    }


    /**
     * Define the hooks
     */
    function handlerRedirect(\HTRouter\Request $request) {
        // Internal redirect handler. Needed?
        return \HTRouter::STATUS_DECLINED;
    }

    function preConfig(\HTRouter\Request $request) {
        // Not needed. Only used for RewriteMap
        return \HTRouter::STATUS_DECLINED;
    }

    function postConfig(\HTRouter\Request $request) {
        // Not needed. Only used for RewriteMap and RewriteLogs
        return \HTRouter::STATUS_DECLINED;
    }

    function childInit(\HTRouter\Request $request) {
        // Not needed. Only used for RewriteMap
        return \HTRouter::STATUS_DECLINED;
    }

    function fixUp(\HTRouter\Request $request) {
        if ($request->getRewriteEngine() == false) {
            return \HTRouter::STATUS_DECLINED;
        }

        // [RewriteRules in directory context]
        // @TODO: We should strip the leading directory stuff
        // @TODO: Directory must not start with /:  so=> /my/dir/index.html => dir/index.html when using /my/dir/.htacces

        foreach ($request->getRewriteRule() as $rule) {
            if (! $rule->matches()) continue;
        }

        return \HTRouter::STATUS_DECLINED;
    }

    function mimeType(\HTRouter\Request $request) {
        // (T=) Type is OK, (H=) Handler is not!

        foreach ($request->getRewriteRule() as $rule) {
            if (! $rule->matches()) continue;

            $flag = $rule->getFlag(Flag::TYPE_MIMETYPE);
            if ($flag == null) {
                return \HTRouter::STATUS_DECLINED;
            }

            // Set content-type!
            $request->setContentType($flag->getKey());

            // @TODO: OK or declined?
            return \HTRouter::STATUS_DECLINED;
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