<?php

namespace SNOWGIRL_SHOP\Manager\Item\DataProvider;

use SNOWGIRL_CORE\Mysql\MysqlQuery;
use SNOWGIRL_CORE\Mysql\MysqlQueryExpression;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Manager\Item\DataProvider;

class MysqlDataProvider extends DataProvider
{
    use \SNOWGIRL_CORE\Manager\DataProvider\Traits\MysqlDataProvider;

    public function getPricesByUri(URI $uri): array
    {
        $mysql = $this->manager->getApp()->container->mysql($this->manager->getMasterServices());

        $columns = [];
        $groups = [];

        $pq = $mysql->quote('price');

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

            $columns[] = new MysqlQueryExpression('IF (' . $tmp . ', 1, 0) AS ' . $mysql->quote($groups[] = 'r_' . $r[0] . '_' . $r[1]));
        }

        $columns[] = new MysqlQueryExpression('COUNT(*) AS ' . $mysql->quote('cnt'));

        $where = $uri->getSRC()->getDataProvider('mysql')->getWhere();

        unset($where[URI::PRICE_FROM]);
        unset($where[URI::PRICE_TO]);

        return $mysql->selectMany($this->manager->getEntity()->getTable(), new MysqlQuery([
            'columns' => $columns,
            'where' => $where,
            'groups' => $groups,
        ]));
    }

    public function getTypesByUri(URI $uri, &$map = [], &$current = []): array
    {
        $copy = $this->manager->copy(true)
            ->setMysql($this->manager->getApp()->container->mysql($this->manager->getMasterServices()));

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

        $mysql = $this->manager->getApp()->container->mysql($this->manager->getMasterServices());

        if (in_array(URI::SALES, URI::TYPE_PARAMS)) {
            $copy->addColumn(new MysqlQueryExpression('IF(' . $mysql->quote('old_price') . ' > 0, 1, 0) AS ' . $mysql->quote('is_sales')))
                ->addGroup('is_sales');
            $map[URI::SALES] = 'is_sales';
            $current[URI::SALES] = $uri->get(URI::SALES);
        }

        $exclude = array_merge($map, array_keys($map));

        $copy->addColumn(new MysqlQueryExpression('COUNT(*) AS ' . $mysql->quote('cnt')))
            ->addWhere(array_filter($uri->getSRC()->getDataProvider('mysql')->getWhere(), function ($k) use ($exclude) {
                return !in_array($k, $exclude);
            }, ARRAY_FILTER_USE_KEY));

        return $copy->getItems();
    }
}