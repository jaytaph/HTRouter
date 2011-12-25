<?php
/**
 * Mod_rewrite. This is probably the most important module (and the whole point of the router to begin with)
 */

namespace HTRouter\Module;
use HTRouter\Module;

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
        list ($testString, $condPattern) = explode(" ", $line, 2);

        $entry = new \StdClass();
        $entry->testString = $testString;
        $entry->condPattern = $condPattern;
        $request->appendTempRewriteConditions($entry);
    }

    public function RewriteOptionsDirective(\HTRouter\Request $request, $line) {
        if ($line != "inherit") {
            throw new \UnexpectedValueException("RewriteOptions must be 'inherit'");
        }
        $request->setRewriteOptions("inherit");
    }

    public function RewriteRuleDirective(\HTRouter\Request $request, $line) {
        $args = explode(" ", $line, 3);
        if (count ($args) < 2) {
            // Add optional flags
            $args[] = "";
        }

        $entry = new \StdClass();
        $entry->pattern = array_shift($args);
        $entry->substitution = array_shift($args);
        $entry->flags = array_shift($args);
        $entry->conditions = $request->getTempRewriteConditions();
        $request->appendRewriteRule($entry);

        // Clear the current rewrite conditions
        $request->unsetTempRewriteConditions();
    }


    /**
     * Define the hooks
     */
    function handlerRedirect(\HTRouter\Request $request) {
        // Internal redirect handler
    }

    function preConfig(\HTRouter\Request $request) {
        // Not needed. Only used for RewriteMap
    }

    function postConfig(\HTRouter\Request $request) {
        // Not needed. Only used for RewriteMap and RewriteLogs
    }

    function childInit(\HTRouter\Request $request) {
        // Not needed. Only used for RewriteMap
    }

    function fixUp(\HTRouter\Request $request) {
        // [RewriteRules in directory context]
        // @TODO: We should strip the leading directory stuff
        // @TODO: Directory must not start with /:  so=> /my/dir/index.html => dir/index.html when using /my/dir/.htacces
    }

    function mimeType(\HTRouter\Request $request) {
        // (T=) Type is OK, (H=) Handler is not!

        // Set content-type!
    }

    function uriToFile(\HTRouter\Request $request) {
        // [RewriteRules in server context]
        // @TODO: I don't think this one is neede, since we only do .htaccess context
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