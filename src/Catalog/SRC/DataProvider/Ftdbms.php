<?php

namespace SNOWGIRL_SHOP\Catalog\SRC\DataProvider;

use SNOWGIRL_CORE\Manager;
use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Catalog\SRC\DataProvider;

class Ftdbms extends DataProvider
{
    public function getTypesByUri(URI $uri, &$map = [], &$current = []): array
    {
        // TODO: Implement getTypesByUri() method.
    }

    public function getPricesByUri(URI $uri): array
    {
        // TODO: Implement getPricesByUri() method.
    }

    public function getListByQuery(string $query, bool $prefix = false): array
    {
        $src = $this->manager->copy(true)
            ->setStorage(Manager::STORAGE_FTDBMS)
            ->addColumn('id')
            ->addWhere(new Expr('MATCH(?)', ($prefix ? '' : '*') . $query . '*'));

        if (strlen($query) <= 2) {
            $src->setLimit(1000);
        }

        $ids = $src->getList('id');

        if (is_array($ids)) {
            if ($ids) {
                return $this->manager
                    ->setStorage(Manager::STORAGE_RDBMS)
                    ->addWhere([$this->manager->getEntity()->getPk() => $ids])
                    ->getObjects();
            }

            return [];
        }

        return [];
    }
}