<?php

namespace SNOWGIRL_SHOP\Catalog\SRC;

use SNOWGIRL_SHOP\Catalog\SRC;

abstract class DataProvider
{
    protected $src;

    public function __construct(SRC $src)
    {
        $this->src = $src;
    }

    abstract public function getItemsAttrs();

    abstract public function getWhere($raw = false);

    /**
     * @param bool $cache
     *
     * @return array
     */
    abstract public function getOrder($cache = false);

    abstract public function getTotalCount();
}