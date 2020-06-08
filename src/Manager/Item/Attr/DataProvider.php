<?php

namespace SNOWGIRL_SHOP\Manager\Item\Attr;

use SNOWGIRL_SHOP\Manager\Item\Attr;
use SNOWGIRL_SHOP\Catalog\URI;

/**
 * Class DataProvider
 * @property Attr manager
 * @package SNOWGIRL_SHOP\Manager\Item\Attr
 */
abstract class DataProvider extends \SNOWGIRL_CORE\Manager\DataProvider
{
    abstract public function getFiltersCountsByUri(URI $uri, string $query = null, bool $prefix = false): array;
}