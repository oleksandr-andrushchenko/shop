<?php

namespace SNOWGIRL_SHOP\Manager;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Mysql\MysqlQueryExpression;
use SNOWGIRL_CORE\Mysql\MysqlQuery;
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

        if (!$this->app->container->memcache->has($cacheKey, $list)) {
            $mysql = $this->app->container->mysql;

            if (1 == $perCharLimit) {
                return $this->copy(true)
                    ->setGroups(new MysqlQueryExpression('UPPER(SUBSTR(' . $mysql->quote('name') . ', 1, 1))'))
                    ->setOrders(['name' => SORT_ASC])
                    ->getList();
            }

            $pk = $this->entity->getPk();

            $query = new MysqlQuery(['params' => [++$perCharLimit]]);
            $query->text = implode(' ', [
                $mysql->makeSelectSQL($pk, false, $query->params),
                'FROM (',
                $mysql->makeSelectSQL([
                    '*',
                    new MysqlQueryExpression('@num := IF(IFNULL(@group, \'\') = ' . $mysql->quote('char') . ', IFNULL(@num, 0) + 1, 1) AS ' . $mysql->quote('row')),
                    new MysqlQueryExpression('@group := ' . $mysql->quote('char'))
                ], false, $query->params),
                'FROM (',
                $mysql->makeSelectSQL(['*', new MysqlQueryExpression('UPPER(SUBSTR(' . $mysql->quote('name') . ', 1, 1)) AS ' . $mysql->quote('char'))], false, $query->params),
                $mysql->makeFromSQL(BrandEntity::getTable()),
                ') t',
                $mysql->makeOrderSQL(['name' => SORT_ASC], $query->params),
                ') AS x',
                'WHERE x.row < ?'
            ]);

            $list = array_map(function ($row) use ($pk) {
                return $row[$pk];
            }, $mysql->reqToArrays($query));

            $this->app->container->memcache->set($cacheKey, $list);
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