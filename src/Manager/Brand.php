<?php

namespace SNOWGIRL_SHOP\Manager;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_CORE\Service\Storage\Query;
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

        /** @var BrandEntity[] $items */
        $items = $this->populateList($this->app->services->mcms->call($this->getNonEmptyGroupedByFirstChar($perCharLimit), function () use ($perCharLimit) {
            $db = $this->app->services->rdbms;

            if (1 == $perCharLimit) {
                return $this->copy(true)
                    ->setGroups(new Expr('UPPER(SUBSTR(' . $db->quote('name') . ', 1, 1))'))
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
                    new Expr('@num := IF(IFNULL(@group, \'\') = ' . $db->quote('char') . ', IFNULL(@num, 0) + 1, 1) AS ' . $db->quote('row')),
                    new Expr('@group := ' . $db->quote('char'))
                ], false, $query->params),
                'FROM (',
                $db->makeSelectSQL(['*', new Expr('UPPER(SUBSTR(' . $db->quote('name') . ', 1, 1)) AS ' . $db->quote('char'))], false, $query->params),
                $db->makeFromSQL(BrandEntity::getTable()),
                ') t',
                $db->makeOrderSQL(['name' => SORT_ASC], $query->params),
                ') AS x',
                'WHERE x.row < ?'
            ]);

            return array_map(function ($row) use ($pk) {
                return $row[$pk];
            }, $db->req($query)->reqToArrays());
        }));

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