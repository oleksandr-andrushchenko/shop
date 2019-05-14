<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 4/4/19
 * Time: 11:20 PM
 */

namespace SNOWGIRL_SHOP\Manager\Item\DataProvider;

use SNOWGIRL_CORE\Manager;
use SNOWGIRL_CORE\Service\Storage\Query;
use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Manager\Item\DataProvider;

/**
 * Class Rdbms
 *
 * @package SNOWGIRL_SHOP\Manager\Item\DataProvider
 */
class Rdbms extends DataProvider
{
    use Manager\DataProvider\Traits\Rdbms;

    public function getPricesByUri(URI $uri): array
    {
        $db = $this->manager->getApp()->services->rdbms(null, null, $this->manager->getMasterServices());

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

        $where = $uri->getSRC()->getDataProvider('rdbms')->getWhere();

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
        $copy = $this->manager->copy(true)->setStorage(Manager::STORAGE_RDBMS);

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

        $db = $this->manager->getApp()->services->rdbms(null, null, $this->manager->getMasterServices());

        if (in_array(URI::SALES, URI::TYPE_PARAMS)) {
            $copy->addColumn(new Expr('IF(' . $db->quote('old_price') . ' > 0, 1, 0) AS ' . $db->quote('is_sales')))
                ->addGroup('is_sales');
            $map[URI::SALES] = 'is_sales';
            $current[URI::SALES] = $uri->get(URI::SALES);
        }

        $exclude = array_merge($map, array_keys($map));

        $copy->addColumn(new Expr('COUNT(*) AS ' . $db->quote('cnt')))
            ->addWhere(array_filter($uri->getSRC()->getDataProvider('rdbms')->getWhere(), function ($k) use ($exclude) {
                return !in_array($k, $exclude);
            }, ARRAY_FILTER_USE_KEY));

        return $copy->getItems();
    }
}