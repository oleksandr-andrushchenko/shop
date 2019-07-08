<?php

namespace SNOWGIRL_SHOP\Manager\Item\DataProvider;

use SNOWGIRL_CORE\Manager;
use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Manager\Item\DataProvider;

class Ftdbms extends DataProvider
{
    use Manager\DataProvider\Traits\Ftdbms;

    public function getTypesByUri(URI $uri, &$map = [], &$current = []): array
    {
        // TODO: Implement getTypesByUri() method.
    }

    public function getPricesByUri(URI $uri): array
    {
        // TODO: Implement getPricesByUri() method.
    }
}