<?php

namespace SNOWGIRL_SHOP\Util;

class Sphinx extends \SNOWGIRL_CORE\Util\Sphinx
{
    protected function preGenerateConfig()
    {
        /** @var \SNOWGIRL_SHOP\App $app */
        $app->managers->categories->syncTree();
    }
}