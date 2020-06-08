<?php

namespace SNOWGIRL_SHOP\Manager\Item\DataProvider;

use SNOWGIRL_CORE\Query;
use SNOWGIRL_CORE\Query\Expression;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Manager\Item\DataProvider;

class DbDataProvider extends DataProvider
{
    use \SNOWGIRL_CORE\Manager\DataProvider\Traits\DbDataProvider;

    public function getPricesByUri(URI $uri): array
    {
        $db = $this->manager->getApp()->container->db($this->manager->getMasterServices());

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

            $columns[] = new Expression('IF (' . $tmp . ', 1, 0) AS ' . $db->quote($groups[] = 'r_' . $r[0] . '_' . $r[1]));
        }

        $columns[] = new Expression('COUNT(*) AS ' . $db->quote('cnt'));

        $where = $uri->getSRC()->getDataProvider('db')->getWhere();

        unset($where[URI::PRICE_FROM]);
        unset($where[URI::PRICE_TO]);

        return $db->selectMany($this->manager->getEntity()->getTable(), new Query([
            'columns' => $columns,
            'where' => $where,
            'groups' => $groups,
        ]));
    }

    public function getTypesByUri(URI $uri, &$map = [], &$current = []): array
    {
        $copy = $this->manager->copy(true)
            ->setDb($this->manager->getApp()->container->db($this->manager->getMasterServices()));

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

        $db = $this->manager->getApp()->container->db($this->manager->getMasterServices());

        if (in_array(URI::SALES, URI::TYPE_PARAMS)) {
            $copy->addColumn(new Expression('IF(' . $db->quote('old_price') . ' > 0, 1, 0) AS ' . $db->quote('is_sales')))
                ->addGroup('is_sales');
            $map[URI::SALES] = 'is_sales';
            $current[URI::SALES] = $uri->get(URI::SALES);
        }

        $exclude = array_merge($map, array_keys($map));

        $copy->addColumn(new Expression('COUNT(*) AS ' . $db->quote('cnt')))
            ->addWhere(array_filter($uri->getSRC()->getDataProvider('db')->getWhere(), function ($k) use ($exclude) {
                return !in_array($k, $exclude);
            }, ARRAY_FILTER_USE_KEY));

        return $copy->getItems();
    }
}