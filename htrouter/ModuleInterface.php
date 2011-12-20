<?php

namespace HTRouter;

interface ModuleInterface {
    public function getName();

    public function init(\HTRouter $router);
}