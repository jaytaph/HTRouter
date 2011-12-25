<?php

namespace HTRouter;

interface ModuleInterface {
    public function getAliases();

    public function init(\HTRouter $router);
}