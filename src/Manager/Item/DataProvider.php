<?php

namespace SNOWGIRL_SHOP\Manager\Item;

use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Manager\Item as ItemManager;

/**
 * Class DataProvider
 *
 * @property ItemManager $manager
 * @package SNOWGIRL_SHOP\Manager\Item
 */
abstract class DataProvider extends \SNOWGIRL_CORE\Manager\DataProvider
{
    abstract public function getPricesByUri(URI $uri): array;

    abstract public function getTypesByUri(URI $uri, &$map = [], &$current = []): array;
}