<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 4/4/19
 * Time: 11:20 PM
 */

namespace SNOWGIRL_SHOP\Manager\Item\DataProvider;

use SNOWGIRL_CORE\Service\Nosql\Mongo;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Manager\Item\DataProvider;

/**
 * Class Nosql
 *
 * @package SNOWGIRL_SHOP\Manager\Item\DataProvider
 */
class Nosql extends DataProvider
{
    use \SNOWGIRL_CORE\Manager\DataProvider\Traits\Nosql;

    public function getPricesByUri(URI $uri): array
    {
        $pipeline = [];

        /** @var Mongo $db */
        $db = $this->manager->getApp()->services->nosql(null, null, $this->manager->getMasterServices());

        $where = $uri->getSRC()->getDataProvider('nosql')->getWhere();
        unset($where[URI::PRICE_FROM]);
        unset($where[URI::PRICE_TO]);

        $pipeline[] = ['$match' => $where];

        $groups = [];

        $ranges = $this->manager->getPriceRanges();
        $s = count($ranges) - 1;

        foreach ($ranges as $k => $r) {
            $name = 'r_' . $r[0] . '_' . $r[1];

            if (0 == $k) {
                $expr = ['$lte' => ['$price', (int)$r[1]]];
            } elseif ($s == $k) {
                $expr = ['$gt' => ['$price', (int)$r[0]]];
            } else {
                $expr = ['$and' => [['$gt' => ['$price', (int)$r[0]]], ['$lte' => ['$price', (int)$r[1]]]]];
            }

            $groups[$name] = ['$cond' => [$expr, 1, 0]];
        }

//        print_r($groups);die;

        $pipeline[] = ['$group' => ['_id' => $groups, 'cnt' => ['$sum' => 1]]];

        return array_map(function ($item) {
            $item = array_merge($item, $item['_id']);
            unset($item['_id']);
            return $item;
        }, $db->aggregate($this->manager->getEntity()->getTable(), $pipeline));
    }

    public function getTypesByUri(URI $uri, &$map = [], &$current = []): array
    {
        $groups = [];

        if (in_array(URI::SPORT, URI::TYPE_PARAMS)) {
            $groups['is_sport'] = '$is_sport';
            $map[URI::SPORT] = 'is_sport';
            $current[URI::SPORT] = $uri->get(URI::SPORT);
        }

        if (in_array(URI::SIZE_PLUS, URI::TYPE_PARAMS)) {
            $groups['is_size_plus'] = '$is_size_plus';
            $map[URI::SIZE_PLUS] = 'is_size_plus';
            $current[URI::SIZE_PLUS] = $uri->get(URI::SIZE_PLUS);
        }

        if (in_array(URI::SALES, URI::TYPE_PARAMS)) {
            $groups['is_sales'] = ['$cond' => [['$gt' => ['$old_price', 0]], 1, 0]];
            $map[URI::SALES] = 'is_sales';
            $current[URI::SALES] = $uri->get(URI::SALES);
        }

        if (!$groups) {
            return [];
        }

        $pipeline = [];

        /** @var Mongo $db */
        $db = $this->manager->getApp()->services->nosql(null, null, $this->manager->getMasterServices());

        $exclude = array_merge($map, array_keys($map));

        $where = array_filter($uri->getSRC()->getDataProvider('nosql')->getWhere(), function ($k) use ($exclude) {
            return !in_array($k, $exclude);
        }, ARRAY_FILTER_USE_KEY);

        if ($where) {
            $pipeline[] = ['$match' => $where];
        }

        $pipeline[] = ['$group' => ['_id' => $groups, 'cnt' => ['$sum' => 1]]];

        return array_map(function ($item) {
            $item = array_merge($item, $item['_id']);
            unset($item['_id']);
            return $item;
        }, $db->aggregate($this->manager->getEntity()->getTable(), $pipeline));
    }
}