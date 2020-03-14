<?php

namespace SNOWGIRL_SHOP\Manager;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Query\Expression;
use SNOWGIRL_CORE\Query;
use SNOWGIRL_SHOP\Entity\Brand as BrandEntity;
use SNOWGIRL_SHOP\Manager\Item\Attr;

/**
 * Class Brand
 *
 * @property BrandEntity $entity
 * @method static Brand getItem($id)
 * @method Brand copy($clear = false)
 * @method Brand clear()
 * @method Brand setLimit($limit)
 * @method Brand setWhere($where)
 * @method BrandEntity[] getObjects()
 * @method BrandEntity getObject()
 * @method BrandEntity[] populateList($id)
 * @method BrandEntity find($id)
 * @method BrandEntity[] findMany(array $id)
 * @package SNOWGIRL_SHOP\Manager
 */
class Brand extends Attr
{
    public function getNonEmptyGroupedByFirstChar($perCharLimit)
    {
        return $this->entity->getTable() . '-non-empty-' . $perCharLimit;
    }

    /**
     * @param int $perCharLimit
     *
     * @return Entity[]|BrandEntity[]
     */
    public function getNonEmptyGroupedByFirstCharObjects($perCharLimit = 10)
    {
        $output = [];

        $this->clear();

        $cacheKey = $this->getNonEmptyGroupedByFirstChar($perCharLimit);

        if (!$this->app->container->cache->has($cacheKey, $list)) {
            $db = $this->app->container->db;

            if (1 == $perCharLimit) {
                return $this->copy(true)
                    ->setGroups(new Expression('UPPER(SUBSTR(' . $db->quote('name') . ', 1, 1))'))
                    ->setOrders(['name' => SORT_ASC])
                    ->getList();
            }

            $pk = $this->entity->getPk();

            $query = new Query(['params' => [++$perCharLimit]]);
            $query->text = implode(' ', [
                $db->makeSelectSQL($pk, false, $query->params),
                'FROM (',
                $db->makeSelectSQL([
                    '*',
                    new Expression('@num := IF(IFNULL(@group, \'\') = ' . $db->quote('char') . ', IFNULL(@num, 0) + 1, 1) AS ' . $db->quote('row')),
                    new Expression('@group := ' . $db->quote('char'))
                ], false, $query->params),
                'FROM (',
                $db->makeSelectSQL(['*', new Expression('UPPER(SUBSTR(' . $db->quote('name') . ', 1, 1)) AS ' . $db->quote('char'))], false, $query->params),
                $db->makeFromSQL(BrandEntity::getTable()),
                ') t',
                $db->makeOrderSQL(['name' => SORT_ASC], $query->params),
                ') AS x',
                'WHERE x.row < ?'
            ]);

            $list = array_map(function ($row) use ($pk) {
                return $row[$pk];
            }, $db->reqToArrays($query));

            $this->app->container->cache->set($cacheKey, $list);
        }

        /** @var BrandEntity[] $items */
        $items = $this->populateList($list);

        foreach ($items as $item) {
            $char = mb_strtoupper(mb_substr($item->getName(), 0, 1));

            if (!isset($output[$char])) {
                $output[$char] = [];
            }

            $output[$char][] = $item;
        }

        ksort($output);

        return $output;
    }
}