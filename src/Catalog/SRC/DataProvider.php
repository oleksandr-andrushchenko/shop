<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 4/2/19
 * Time: 8:01 PM
 */

namespace SNOWGIRL_SHOP\Catalog\SRC;

use SNOWGIRL_SHOP\Catalog\SRC;

/**
 * Class DataProvider
 *
 * @package SNOWGIRL_SHOP\Catalog\SRC
 */
abstract class DataProvider
{
    protected $src;

    public function __construct(SRC $src)
    {
        $this->src = $src;
    }

    abstract public function getItemsAttrs();

    abstract public function getWhere($raw = false);

    abstract public function getOrder($cache = false);

    abstract public function getTotalCount();
}