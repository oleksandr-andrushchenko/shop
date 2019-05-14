<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 4/19/19
 * Time: 3:08 PM
 */

namespace SNOWGIRL_SHOP\Util;

/**
 * Class Sphinx
 *
 * @package SNOWGIRL_SHOP\Util
 */
class Sphinx extends \SNOWGIRL_CORE\Util\Sphinx
{
    protected function preGenerateConfig()
    {
        /** @var \SNOWGIRL_SHOP\App $app */
        $app->managers->categories->syncTree();
    }
}