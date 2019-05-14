<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 4/4/19
 * Time: 10:42 PM
 */

namespace SNOWGIRL_SHOP\Manager\Item\Attr;

use SNOWGIRL_SHOP\Manager\Item\Attr as ItemAttrManager;
use SNOWGIRL_SHOP\Catalog\URI;

/**
 * Class DataProvider
 *
 * @package SNOWGIRL_SHOP\Manager\Item\Attr
 */
abstract class DataProvider
{
    /**
     * @var ItemAttrManager
     */
    protected $manager;

    public function __construct(ItemAttrManager $manager)
    {
        $this->manager = $manager;
    }

    abstract public function getFiltersCountsByUri(URI $uri, $query = null, $prefix = false);
}