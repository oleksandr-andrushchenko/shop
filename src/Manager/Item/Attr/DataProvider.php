<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 4/4/19
 * Time: 10:42 PM
 */

namespace SNOWGIRL_SHOP\Manager\Item\Attr;

use SNOWGIRL_SHOP\Manager\Item\Attr;
use SNOWGIRL_SHOP\Catalog\URI;

/**
 * Class DataProvider
 *
 * @property Attr manager
 * @package SNOWGIRL_SHOP\Manager\Item\Attr
 */
abstract class DataProvider extends \SNOWGIRL_CORE\Manager\DataProvider
{
    abstract public function getFiltersCountsByUri(URI $uri, $query = null, $prefix = false);
}