<?php

namespace SNOWGIRL_SHOP\Manager\Item\DataProvider;

use SNOWGIRL_CORE\Service\Storage\Query;
use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Manager\Item\DataProvider;

class Mysql extends DataProvider
{
    use \SNOWGIRL_CORE\Manager\DataProvider\Traits\Mysql;

    public function getPricesByUri(URI $uri): array
    {
        $db = $this->manager->getApp()->storage->mysql(null, $this->manager->getMasterServices());

        $columns = [];
        $groups = [];

        $pq = $db->quote('price');

        $ranges = $this->manager->getPriceRanges();
        $s = count($ranges) - 1;

        foreach ($ranges as $k => $r) {
            if (0 == $k) {
                $tmp = $pq . ' <= ' . $r[1];
            } elseif ($s == $k) {
                $tmp = $pq . ' > ' . $r[0];
            } else {
                $tmp = $pq . ' > ' . $r[0] . ' AND ' . $pq . ' <= ' . $r[1];
            }

            $columns[] = new Expr('IF (' . $tmp . ', 1, 0) AS ' . $db->quote($groups[] = 'r_' . $r[0] . '_' . $r[1]));
        }

        $columns[] = new Expr('COUNT(*) AS ' . $db->quote('cnt'));

        $where = $uri->getSRC()->getDataProvider('mysql')->getWhere();

        unset($where[URI::PRICE_FROM]);
        unset($where[URI::PRICE_TO]);

        return $db->selectMany($this->manager->getEntity()->getTable(), new Query([
            'columns' => $columns,
            'where' => $where,
            'groups' => $groups
        ]));
    }

    public function getTypesByUri(URI $uri, &$map = [], &$current = []): array
    {
        $copy = $this->manager->copy(true)
            ->setStorage($this->manager->getApp()->storage->mysql(null, $this->manager->getMasterServices()));

        $map = [];
        $current = [];

        if (in_array(URI::SPORT, URI::TYPE_PARAMS)) {
            $copy->addColumn('is_sport')
                ->addGroup('is_sport');
            $map[URI::SPORT] = 'is_sport';
            $current[URI::SPORT] = $uri->get(URI::SPORT);
        }

        if (in_array(URI::SIZE_PLUS, URI::TYPE_PARAMS)) {
            $copy->addColumn('is_size_plus')
                ->addGroup('is_size_plus');
            $map[URI::SIZE_PLUS] = 'is_size_plus';
            $current[URI::SIZE_PLUS] = $uri->get(URI::SIZE_PLUS);
        }

        $db = $this->manager->getApp()->storage->mysql(null, $this->manager->getMasterServices());

        if (in_array(URI::SALES, URI::TYPE_PARAMS)) {
            $copy->addColumn(new Expr('IF(' . $db->quote('old_price') . ' > 0, 1, 0) AS ' . $db->quote('is_sales')))
                ->addGroup('is_sales');
            $map[URI::SALES] = 'is_sales';
            $current[URI::SALES] = $uri->get(URI::SALES);
        }

        $exclude = array_merge($map, array_keys($map));

        $copy->addColumn(new Expr('COUNT(*) AS ' . $db->quote('cnt')))
            ->addWhere(array_filter($uri->getSRC()->getDataProvider('mysql')->getWhere(), function ($k) use ($exclude) {
                return !in_array($k, $exclude);
            }, ARRAY_FILTER_USE_KEY));

        return $copy->getItems();
    }
}